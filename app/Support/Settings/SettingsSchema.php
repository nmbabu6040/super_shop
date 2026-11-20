<?php

declare(strict_types=1);

namespace App\Support\Settings;

use InvalidArgumentException;

/**
 * Reads config/settings_schema.php. Only settings declared there can ever be written
 * through the admin UI (a whitelist), with their own validation rules.
 */
final class SettingsSchema
{
    /**
     * @return list<string>
     */
    public static function groupKeys(): array
    {
        return array_keys(config('settings_schema.groups', []));
    }

    public static function hasGroup(string $group): bool
    {
        return in_array($group, self::groupKeys(), true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(string $group): array
    {
        $fields = config("settings_schema.groups.{$group}.fields");

        if (! is_array($fields)) {
            throw new InvalidArgumentException("Settings group [{$group}] is not defined.");
        }

        $normalizedFields = [];

        foreach ($fields as $key => $field) {
            $normalizedFields[$key] = array_merge([
                'type' => 'string',
                'input' => 'text',
                'rules' => ['nullable', 'string', 'max:255'],
                'public' => false,
                'readonly' => false,
                'default' => null,
                'options' => [],
                'option_labels' => 'lang',
            ], $field);
        }

        return $normalizedFields;
    }
}
