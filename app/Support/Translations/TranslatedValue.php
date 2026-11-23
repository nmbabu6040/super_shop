<?php

declare(strict_types=1);

namespace App\Support\Translations;

use App\Support\Localization;
use JsonSerializable;
use Stringable;

/**
 * A text stored in several languages, e.g. ["en" => "Rice", "bn" => "চাল"].
 * Echoing it (Blade {{ $category->name }}) prints the current language and falls back to
 * English (then any other filled language) when the current one is empty.
 */
final class TranslatedValue implements JsonSerializable, Stringable
{
    /**
     * @param  array<string, string|null>  $translations
     */
    public function __construct(private readonly array $translations)
    {
    }

    public function get(?string $locale = null, bool $withFallback = true): ?string
    {
        $locale ??= app()->getLocale();

        $text = $this->cleaned($locale);

        if ($text !== null || ! $withFallback) {
            return $text;
        }

        $candidates = array_unique([
            (string) config('app.fallback_locale'),
            (string) config('app.locale'),
            ...Localization::supportedLocaleCodes(),
        ]);

        foreach ($candidates as $candidate) {
            $text = $this->cleaned($candidate);

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return $this->translations;
    }

    public function isEmpty(): bool
    {
        return $this->get(withFallback: true) === null;
    }

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->translations;
    }

    public function __toString(): string
    {
        return $this->get() ?? '';
    }

    private function cleaned(string $locale): ?string
    {
        $text = $this->translations[$locale] ?? null;

        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
