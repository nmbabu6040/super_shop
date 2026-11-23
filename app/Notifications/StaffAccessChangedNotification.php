<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Security notice sent to a staff member whose roles or branch access were changed.
 */
class StaffAccessChangedNotification extends AppNotification
{
    public function __construct(private readonly string $changedByName)
    {
    }

    public static function notificationType(): string
    {
        return 'staff_access_changed';
    }

    protected function titleKey(): string
    {
        return 'notifications.messages.staff_access_changed.title';
    }

    protected function messageKey(): string
    {
        return 'notifications.messages.staff_access_changed.message';
    }

    protected function params(): array
    {
        return ['by' => $this->changedByName];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__($this->titleKey()))
            ->greeting(__('invitation.mail_greeting', ['name' => $notifiable->name]))
            ->line(__($this->messageKey(), $this->params()));
    }
}
