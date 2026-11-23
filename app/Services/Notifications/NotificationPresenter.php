<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;

/**
 * Turns a stored notification into display text in the viewer's current language.
 * Notifications store translation KEYS and a route NAME (not text or URLs), so each viewer
 * sees their own language and a stored notification can never point to an external address.
 */
final class NotificationPresenter
{
    /**
     * @return array{id: string, title: string, message: string, url: ?string, is_read: bool, time: string}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $parameters = is_array($data['params'] ?? null) ? $data['params'] : [];

        return [
            'id' => $notification->id,
            'title' => __($data['title_key'] ?? 'notifications.unknown_title', $parameters),
            'message' => __($data['message_key'] ?? 'notifications.unknown_message', $parameters),
            'url' => $this->urlFor($data['route'] ?? null),
            'is_read' => $notification->read_at !== null,
            'time' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    private function urlFor(mixed $route): ?string
    {
        if (! is_array($route) || ! isset($route['name']) || ! is_string($route['name']) || ! Route::has($route['name'])) {
            return null;
        }

        try {
            return route($route['name'], is_array($route['parameters'] ?? null) ? $route['parameters'] : []);
        } catch (UrlGenerationException) {
            return null;
        }
    }
}
