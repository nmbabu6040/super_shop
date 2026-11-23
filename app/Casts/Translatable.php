<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Localization;
use App\Support\Translations\TranslatedValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a multi-language text in a JSON column and reads it back as a TranslatedValue.
 * Only supported languages are kept; empty texts are dropped.
 *
 * @implements CastsAttributes<TranslatedValue, array<string, string|null>|TranslatedValue|string|null>
 */
class Translatable implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): TranslatedValue
    {
        $decoded = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);

        return new TranslatedValue(is_array($decoded) ? $decoded : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = $this->normalize($value);

        return $cleaned === []
            ? null
            : json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Two values are equal when their cleaned translations are equal, so saving an unchanged
     * text neither marks the model dirty nor writes a pointless audit entry.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->normalize($firstValue) === $this->normalize($secondValue);
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function normalize(mixed $value): array
    {
        if ($value instanceof TranslatedValue) {
            $value = $value->toArray();
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);

            // A plain (non-JSON) string is taken as the text of the current language.
            $value = is_array($decoded) ? $decoded : [app()->getLocale() => $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $cleaned = [];

        $localeCodes = Localization::supportedLocaleCodes();

        // Keep English first, then the remaining supported languages.
        $orderedLocaleCodes = [
            ...array_values(array_filter(
                ['en'],
                fn(string $localeCode): bool => in_array($localeCode, $localeCodes, true)
            )),
            ...array_values(array_filter(
                $localeCodes,
                fn(string $localeCode): bool => $localeCode !== 'en'
            )),
        ];

        foreach ($orderedLocaleCodes as $localeCode) {
            $text = trim((string) ($value[$localeCode] ?? ''));

            if ($text !== '') {
                $cleaned[$localeCode] = $text;
            }
        }

        return $cleaned;
    }
}
