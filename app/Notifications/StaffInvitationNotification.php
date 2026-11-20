<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email for a new staff member. The URL is built before the notification is
 * created, so the message does not depend on the current request.
 */
class StaffInvitationNotification extends Notification
{
    public function __construct(
        public readonly string $acceptUrl,
        public readonly ?string $inviterName = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name');

        return (new MailMessage())
            ->subject(__('invitation.mail_subject', ['app' => $appName]))
            ->greeting(__('invitation.mail_greeting', ['name' => $notifiable->name]))
            ->line(__('invitation.mail_intro', ['app' => $appName]))
            ->action(__('invitation.mail_action'), $this->acceptUrl)
            ->line(__('invitation.mail_expiry', ['days' => StaffInvitationLifetime::DAYS]))
            ->line(__('invitation.mail_outro'));
    }
}
