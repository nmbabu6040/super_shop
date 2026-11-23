<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Notifications\Notification;

/**
 * Base class of every in-app/email notification of the application.
 * Channels come from the notification type registry and the user's preferences.
 * The database payload holds translation keys, so it is shown in the viewer's language.
 */
abstract class AppNotification extends Notification
{
    /**
     * Key of the type in config/notification_types.php.
     */
    abstract public static function notificationType(): string;

    abstract protected function titleKey(): string;

    abstract protected function messageKey(): string;

    /**
     * Values for the translation placeholders.
     *
     * @return array<string, string>
     */
    protected function params(): array
    {
        return [];
    }

    /**
     * Route the notification links to (a route NAME, never a URL).
     *
     * @return array{name: string, parameters?: array<string, mixed>}|null
     */
    protected function linkRoute(): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->channelsFor($notifiable, static::notificationType());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => static::notificationType(),
            'title_key' => $this->titleKey(),
            'message_key' => $this->messageKey(),
            'params' => $this->params(),
            'route' => $this->linkRoute(),
        ];
    }
}
