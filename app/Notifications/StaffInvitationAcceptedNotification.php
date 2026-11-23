<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Tells the person who invited a staff member that the account is now active.
 */
class StaffInvitationAcceptedNotification extends AppNotification
{
    public function __construct(private readonly string $acceptedName)
    {
    }

    public static function notificationType(): string
    {
        return 'staff_invitation_accepted';
    }

    protected function titleKey(): string
    {
        return 'notifications.messages.staff_invitation_accepted.title';
    }

    protected function messageKey(): string
    {
        return 'notifications.messages.staff_invitation_accepted.message';
    }

    protected function params(): array
    {
        return ['name' => $this->acceptedName];
    }

    protected function linkRoute(): ?array
    {
        return ['name' => 'admin.users.index'];
    }
}
