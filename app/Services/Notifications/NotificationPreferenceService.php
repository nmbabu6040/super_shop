<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use InvalidArgumentException;

/**
 * Decides on which channels a user receives a notification type.
 * Mandatory channels (security notices) can never be switched off.
 */
final class NotificationPreferenceService
{
    /** Notification channel => preference column. */
    private const CHANNEL_COLUMNS = [
        'mail' => 'email_enabled',
        'database' => 'in_app_enabled',
    ];

    /** Form field => notification channel. */
    private const FORM_CHANNELS = [
        'email' => 'mail',
        'in_app' => 'database',
    ];

    /**
     * @return array<string, array{channels: list<string>, mandatory: list<string>, permission: ?string}>
     */
    public function registry(): array
    {
        return config('notification_types', []);
    }

    /**
     * @return list<string>
     */
    public function channelsFor(object $notifiable, string $type): array
    {
        $definition = $this->registry()[$type] ?? throw new InvalidArgumentException("Unknown notification type [{$type}].");

        $preference = $notifiable instanceof User
            ? NotificationPreference::query()->where('user_id', $notifiable->getKey())->where('type', $type)->first()
            : null;

        $channels = [];

        foreach (self::CHANNEL_COLUMNS as $channel => $column) {
            if (! in_array($channel, $definition['channels'], true)) {
                continue;
            }

            $isMandatory = in_array($channel, $definition['mandatory'] ?? [], true);
            $isEnabled = $preference?->{$column} ?? true;

            if ($isMandatory || $isEnabled) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * Types this user may receive (and therefore configure).
     *
     * @return array<string, array{channels: list<string>, mandatory: list<string>, permission: ?string}>
     */
    public function typesFor(User $user): array
    {
        return array_filter(
            $this->registry(),
            static fn (array $definition): bool => $definition['permission'] === null
                || $user->hasAnyPermission([$definition['permission']])
        );
    }

    /**
     * Current settings of the user, ready for the form.
     *
     * @return array<string, array<string, array{enabled: bool, locked: bool, available: bool}>>
     */
    public function formStateFor(User $user): array
    {
        $preferences = NotificationPreference::query()->where('user_id', $user->getKey())->get()->keyBy('type');
        $state = [];

        foreach ($this->typesFor($user) as $type => $definition) {
            foreach (self::FORM_CHANNELS as $formField => $channel) {
                $column = self::CHANNEL_COLUMNS[$channel];
                $isMandatory = in_array($channel, $definition['mandatory'] ?? [], true);

                $state[$type][$formField] = [
                    'available' => in_array($channel, $definition['channels'], true),
                    'locked' => $isMandatory,
                    'enabled' => $isMandatory || ($preferences->get($type)?->{$column} ?? true),
                ];
            }
        }

        return $state;
    }

    /**
     * Saves the user's choices. Unknown types, types the user may not receive, unavailable
     * channels and mandatory channels in the input are ignored.
     *
     * @param  array<string, array<string, mixed>>  $input  preferences[type][email|in_app]
     */
    public function update(User $user, array $input): void
    {
        foreach ($this->typesFor($user) as $type => $definition) {
            $attributes = [];

            foreach (self::FORM_CHANNELS as $formField => $channel) {
                $isEditable = in_array($channel, $definition['channels'], true)
                    && ! in_array($channel, $definition['mandatory'] ?? [], true);

                if ($isEditable && isset($input[$type]) && array_key_exists($formField, $input[$type])) {
                    $attributes[self::CHANNEL_COLUMNS[$channel]] = filter_var($input[$type][$formField], FILTER_VALIDATE_BOOL);
                }
            }

            if ($attributes !== []) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->getKey(), 'type' => $type],
                    $attributes
                );
            }
        }
    }
}
