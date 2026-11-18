<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/**
 * Database-driven application settings with typing, optional encryption and caching.
 *
 * Security rules enforced here:
 *  - the cache stores the raw stored value, so encrypted settings stay encrypted in the cache;
 *  - an encrypted setting can never be public;
 *  - floats are rejected (use numeric strings for money/decimal values).
 */
final class SettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $storedRow = $this->storedRows()[$this->rowKey($group, $key)] ?? null;

        if ($storedRow === null) {
            return $default;
        }

        $value = $this->castFromStorage(
            $storedRow['value'],
            SettingType::from($storedRow['type']),
            $storedRow['is_encrypted'],
        );

        return $value ?? $default;
    }

    /**
     * Create or update a setting.
     *
     * Passing null for $isEncrypted / $isPublic keeps the existing flag (false for new settings),
     * so an update can never silently downgrade an encrypted setting.
     */
    public function set(
        string $group,
        string $key,
        mixed $value,
        ?SettingType $type = null,
        ?bool $isEncrypted = null,
        ?bool $isPublic = null,
    ): Setting {
        $setting = Setting::query()->firstOrNew(['group' => $group, 'key' => $key]);

        $resolvedIsEncrypted = $isEncrypted ?? ($setting->exists ? $setting->is_encrypted : false);
        $resolvedIsPublic = $isPublic ?? ($setting->exists ? $setting->is_public : false);

        if ($resolvedIsEncrypted && $resolvedIsPublic) {
            throw new InvalidArgumentException('An encrypted setting can never be public.');
        }

        $resolvedType = $type ?? $this->inferType($value);

        $setting->fill([
            'value' => $this->serializeForStorage($value, $resolvedType, $resolvedIsEncrypted),
            'type' => $resolvedType,
            'is_encrypted' => $resolvedIsEncrypted,
            'is_public' => $resolvedIsPublic,
            'value_updated_at' => now(),
            'updated_by' => Auth::id(),
        ])->save();

        return $setting;
    }

    public function delete(string $group, string $key): void
    {
        Setting::query()->where('group', $group)->where('key', $key)->get()
            ->each(static fn (Setting $setting) => $setting->delete());
    }

    /**
     * All settings of one group, keyed by setting key. Encrypted values are excluded
     * unless explicitly requested.
     *
     * @return array<string, mixed>
     */
    public function group(string $group, bool $includeEncrypted = false): array
    {
        $groupSettings = [];
        $prefix = $group . '.';

        foreach ($this->storedRows() as $rowKey => $storedRow) {
            if (! str_starts_with($rowKey, $prefix)) {
                continue;
            }

            if ($storedRow['is_encrypted'] && ! $includeEncrypted) {
                continue;
            }

            $groupSettings[substr($rowKey, strlen($prefix))] = $this->castFromStorage(
                $storedRow['value'],
                SettingType::from($storedRow['type']),
                $storedRow['is_encrypted'],
            );
        }

        return $groupSettings;
    }

    /**
     * Settings safe to expose to the storefront, keyed as "group.key".
     *
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $publicSettings = [];

        foreach ($this->storedRows() as $rowKey => $storedRow) {
            if (! $storedRow['is_public'] || $storedRow['is_encrypted']) {
                continue;
            }

            $publicSettings[$rowKey] = $this->castFromStorage(
                $storedRow['value'],
                SettingType::from($storedRow['type']),
                false,
            );
        }

        return $publicSettings;
    }

    /**
     * @return array<string, array{value: ?string, type: string, is_encrypted: bool, is_public: bool}>
     */
    private function storedRows(): array
    {
        return Cache::rememberForever(Setting::CACHE_KEY, static function (): array {
            $rows = [];

            foreach (Setting::query()->get() as $setting) {
                $rows[$setting->group . '.' . $setting->key] = [
                    'value' => $setting->value,
                    'type' => $setting->type->value,
                    'is_encrypted' => $setting->is_encrypted,
                    'is_public' => $setting->is_public,
                ];
            }

            return $rows;
        });
    }

    private function rowKey(string $group, string $key): string
    {
        return $group . '.' . $key;
    }

    private function inferType(mixed $value): SettingType
    {
        return match (true) {
            is_bool($value) => SettingType::Boolean,
            is_int($value) => SettingType::Integer,
            is_array($value) => SettingType::Json,
            default => SettingType::String,
        };
    }

    private function serializeForStorage(mixed $value, SettingType $type, bool $isEncrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException(
                'Floats are not allowed in settings. Pass money/decimal values as numeric strings.'
            );
        }

        $plainValue = match ($type) {
            SettingType::String => is_scalar($value)
                ? (string) $value
                : throw new InvalidArgumentException('A string setting needs a scalar value.'),
            SettingType::Integer => (string) (int) $value,
            SettingType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
            SettingType::Decimal => $this->requireNumericString($value),
            SettingType::Json => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        };

        return $isEncrypted ? Crypt::encryptString($plainValue) : $plainValue;
    }

    private function requireNumericString(mixed $value): string
    {
        if ((is_string($value) || is_int($value)) && is_numeric((string) $value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('A decimal setting needs a numeric string.');
    }

    private function castFromStorage(?string $storedValue, SettingType $type, bool $isEncrypted): mixed
    {
        if ($storedValue === null) {
            return null;
        }

        $plainValue = $isEncrypted ? Crypt::decryptString($storedValue) : $storedValue;

        return match ($type) {
            SettingType::String, SettingType::Decimal => $plainValue,
            SettingType::Integer => (int) $plainValue,
            SettingType::Boolean => filter_var($plainValue, FILTER_VALIDATE_BOOL),
            SettingType::Json => json_decode($plainValue, true, 512, JSON_THROW_ON_ERROR),
        };
    }
}
