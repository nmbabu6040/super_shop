<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\AppNotification;

/**
 * Sends a notification to one or more users, each in their own language.
 */
final class NotificationDispatcher
{
    /**
     * @param  User|iterable<User>  $recipients
     */
    public function send(User|iterable $recipients, AppNotification $notification): void
    {
        $recipientList = $recipients instanceof User ? [$recipients] : $recipients;

        foreach ($recipientList as $recipient) {
            // locale() changes the notification, so every recipient gets their own copy.
            $recipient->notify(
                (clone $notification)->locale($recipient->preferred_locale ?: (string) config('app.locale'))
            );
        }
    }
}
