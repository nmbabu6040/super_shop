<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\UserStatus;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationAcceptedNotification;
use App\Notifications\StaffInvitationLifetime;
use App\Notifications\StaffInvitationNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitation flow for new staff: the administrator never sees or sets a password.
 * The invited person opens a one-time link and chooses their own password.
 */
final class StaffInvitationService
{
    public function __construct(private readonly NotificationDispatcher $notificationDispatcher)
    {
    }

    public function send(User $user, ?User $invitedBy = null): void
    {
        $plainToken = Str::random(64);

        // updateOrCreate: a new invitation replaces (and so invalidates) any earlier one.
        StaffInvitation::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(StaffInvitationLifetime::DAYS),
                'created_by' => $invitedBy?->getKey(),
            ]
        );

        $locale = $user->preferred_locale ?: (string) config('app.locale');

        $acceptUrl = URL::route('invitation.accept', ['locale' => $locale, 'token' => $plainToken]);

        $user->notify(
            (new StaffInvitationNotification($acceptUrl, $invitedBy?->name))->locale($locale)
        );
    }

    /**
     * Finds an open, unexpired invitation of a user who is still waiting to activate.
     */
    public function findValid(string $plainToken): ?StaffInvitation
    {
        return StaffInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->whereHas('user', static fn ($userQuery) => $userQuery->where('status', UserStatus::Pending->value))
            ->with('user')
            ->first();
    }

    /**
     * Sets the chosen password, activates the account and marks the email as verified
     * (opening the emailed link proves the address). The invitation can be used only once.
     * The person who sent the invitation is told that the account is active.
     *
     * @throws ValidationException
     */
    public function accept(string $plainToken, string $password): User
    {
        $invitation = $this->findValid($plainToken);

        if ($invitation === null) {
            throw ValidationException::withMessages(['password' => __('invitation.invalid')]);
        }

        $inviterId = $invitation->created_by;

        $user = DB::transaction(function () use ($invitation, $password): User {
            $user = $invitation->user;

            $user->forceFill([
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $invitation->delete();

            return $user;
        });

        if ($inviterId !== null && $inviterId !== $user->getKey()) {
            $inviter = User::query()->find($inviterId);

            if ($inviter !== null) {
                $this->notificationDispatcher->send($inviter, new StaffInvitationAcceptedNotification($user->name));
            }
        }

        return $user;
    }
}
