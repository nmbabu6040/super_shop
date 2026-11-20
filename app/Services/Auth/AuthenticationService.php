<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\LoginOutcome;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in with email, username or phone number.
 *
 * Security properties:
 *  - failed attempts are rate limited per (identifier + IP);
 *  - the response never reveals whether an account exists (same message, same work done);
 *  - account status (suspended/pending) is revealed only after the password was correct;
 *  - the session id is regenerated on login (session fixation protection).
 */
final class AuthenticationService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    private static ?string $dummyPasswordHash = null;

    /**
     * @throws ValidationException
     */
    public function attempt(string $identifier, string $password, bool $remember, Request $request): User
    {
        $throttleKey = $this->throttleKey($identifier, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            $this->recordHistory(null, LoginOutcome::Throttled, $request);
            event(new Lockout($request));

            $secondsLeft = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', [
                    'seconds' => $secondsLeft,
                    'minutes' => (int) ceil($secondsLeft / 60),
                ]),
            ]);
        }

        $user = $this->findUserByIdentifier($identifier);

        // Always perform one password check so response time does not reveal whether the account exists.
        $passwordMatches = Hash::check($password, $user?->password ?? $this->dummyPasswordHash());

        if ($user === null || ! $passwordMatches) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            $this->recordHistory(
                $user?->getKey(),
                $user === null ? LoginOutcome::FailedUnknownAccount : LoginOutcome::FailedPassword,
                $request
            );

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            $this->recordHistory($user->getKey(), LoginOutcome::BlockedInactive, $request);

            throw ValidationException::withMessages(['identifier' => __('auth.inactive')]);
        }

        RateLimiter::clear($throttleKey);

        if (Hash::needsRehash($user->password)) {
            // The "hashed" cast hashes the plain password again with the current algorithm settings.
            $user->forceFill(['password' => $password])->saveQuietly();
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->recordHistory($user->getKey(), LoginOutcome::Success, $request);

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $normalizedIdentifier = trim($identifier);

        if (str_contains($normalizedIdentifier, '@')) {
            return User::query()->where('email', Str::lower($normalizedIdentifier))->first();
        }

        $normalizedPhone = PhoneNumber::normalize($normalizedIdentifier);

        if ($normalizedPhone !== null) {
            $userByPhone = User::query()->where('phone', $normalizedPhone)->first();

            if ($userByPhone !== null) {
                return $userByPhone;
            }
        }

        return User::query()->where('username', Str::lower($normalizedIdentifier))->first();
    }

    private function throttleKey(string $identifier, Request $request): string
    {
        return Str::transliterate(Str::lower(trim($identifier))) . '|' . $request->ip();
    }

    /**
     * A valid hash of a random password, created once per process, used to keep timing equal
     * when the account does not exist.
     */
    private function dummyPasswordHash(): string
    {
        return self::$dummyPasswordHash ??= Hash::make(Str::random(40));
    }

    private function recordHistory(int|string|null $userId, LoginOutcome $outcome, Request $request): void
    {
        LoginHistory::query()->create([
            'user_id' => $userId,
            'outcome' => $outcome,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }
}
