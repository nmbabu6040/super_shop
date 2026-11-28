<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Daily summary: batches that expired or are about to expire.
 */
class ExpiryAlertNotification extends AppNotification
{
    public function __construct(
        private readonly int $expiredCount,
        private readonly int $soonCount,
        private readonly int $days,
    ) {
    }

    public static function notificationType(): string
    {
        return 'expiry_alert';
    }

    protected function titleKey(): string
    {
        return 'notifications.messages.expiry_alert.title';
    }

    protected function messageKey(): string
    {
        return 'notifications.messages.expiry_alert.message';
    }

    protected function params(): array
    {
        return [
            'expired' => (string) $this->expiredCount,
            'soon' => (string) $this->soonCount,
            'days' => (string) $this->days,
        ];
    }

    protected function linkRoute(): ?array
    {
        return ['name' => 'admin.stock.batches', 'parameters' => ['expiry' => $this->expiredCount > 0 ? 'expired' : 'soon']];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__($this->titleKey()))
            ->greeting(__('invitation.mail_greeting', ['name' => $notifiable->name]))
            ->line(__($this->messageKey(), $this->params()))
            ->action(__('notifications.open'), route('admin.stock.batches', ['expiry' => $this->expiredCount > 0 ? 'expired' : 'soon']));
    }
}
