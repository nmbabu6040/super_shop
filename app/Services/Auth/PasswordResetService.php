<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;

final class PasswordResetService
{
    public function __construct(private readonly UserSessionService $userSessionService)
    {
    }

    /**
     * Sends a reset link when the account exists. The result is intentionally ignored so
     * the response can never reveal whether an email address is registered.
     */
    public function sendResetLink(string $email): void
    {
        PasswordBroker::sendResetLink(['email' => Str::lower(trim($email))]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return string One of the PasswordBroker status constants.
     */
    public function reset(array $credentials): string
    {
        $credentials['email'] = Str::lower(trim($credentials['email']));

        return PasswordBroker::reset($credentials, function (User $user, string $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
                // Invalidates every "remember me" cookie.
                'remember_token' => Str::random(60),
            ])->save();

            // A password reset means the old password may be compromised: sign out everywhere.
            $this->userSessionService->revokeAll($user);

            event(new PasswordReset($user));
        });
    }
}
