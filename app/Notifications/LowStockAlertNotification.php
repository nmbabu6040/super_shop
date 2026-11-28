<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Daily summary: how many products are at or below their reorder level.
 */
class LowStockAlertNotification extends AppNotification
{
    public function __construct(private readonly int $count)
    {
    }

    public static function notificationType(): string
    {
        return 'low_stock_alert';
    }

    protected function titleKey(): string
    {
        return 'notifications.messages.low_stock_alert.title';
    }

    protected function messageKey(): string
    {
        return 'notifications.messages.low_stock_alert.message';
    }

    protected function params(): array
    {
        return ['count' => (string) $this->count];
    }

    protected function linkRoute(): ?array
    {
        return ['name' => 'admin.stock.index', 'parameters' => ['status' => 'low']];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__($this->titleKey()))
            ->greeting(__('invitation.mail_greeting', ['name' => $notifiable->name]))
            ->line(__($this->messageKey(), $this->params()))
            ->action(__('notifications.open'), route('admin.stock.index', ['status' => 'low']));
    }
}
