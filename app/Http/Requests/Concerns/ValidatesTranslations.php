<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Localization;

/**
 * Validation helpers for multi-language fields (name[en], name[bn]).
 * English is the required language; every other language is optional.
 */
trait ValidatesTranslations
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function translatableRules(string $field, bool $required, int $maxLength): array
    {
        $localeCodes = Localization::supportedLocaleCodes();

        $rules = [
            $field => [$required ? 'required' : 'nullable', 'array:' . implode(',', $localeCodes)],
        ];

        foreach ($localeCodes as $localeCode) {
            $isRequired = $required && $localeCode === 'en';

            $rules["{$field}.{$localeCode}"] = [$isRequired ? 'required' : 'nullable', 'string', "max:{$maxLength}"];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function translatableAttributes(string $field, string $label): array
    {
        $attributes = [];

        foreach (config('locales.supported') as $localeCode => $meta) {
            $attributes["{$field}.{$localeCode}"] = "{$label} ({$meta['native_name']})";
        }

        return $attributes;
    }
}
