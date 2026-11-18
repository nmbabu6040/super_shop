<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Central helper for the bilingual (bn/en) URL scheme.
 * Keeps all locale rules in one place so middleware, routes, controllers
 * and Blade components never duplicate them.
 */
final class Localization
{
    /**
     * @return list<string>
     */
    public static function supportedLocaleCodes(): array
    {
        return array_keys(config('locales.supported'));
    }

    public static function isSupported(string $localeCode): bool
    {
        return in_array($localeCode, self::supportedLocaleCodes(), true);
    }

    /**
     * Regex fragment used to constrain the {locale} route parameter, e.g. "bn|en".
     */
    public static function routePattern(): string
    {
        return implode('|', array_map('preg_quote', self::supportedLocaleCodes()));
    }

    public static function sessionKey(): string
    {
        return (string) config('locales.session_key', 'locale');
    }

    /**
     * Decide which language a visitor without a locale URL prefix should get:
     * 1) the language they chose earlier (session),
     * 2) the browser's Accept-Language header, if it matches a supported language,
     * 3) the application default.
     */
    public static function preferredLocaleFor(Request $request): string
    {
        $storedLocale = $request->session()->get(self::sessionKey());

        if (is_string($storedLocale) && self::isSupported($storedLocale)) {
            return $storedLocale;
        }

        if ($request->headers->has('Accept-Language')) {
            // Symfony returns the FIRST candidate when nothing in the header matches.
            // Putting the configured default first makes "no match" fall back to the
            // application default instead of whichever language happens to be listed first.
            $candidateLocaleCodes = array_values(array_unique([
                (string) config('app.locale'),
                ...self::supportedLocaleCodes(),
            ]));

            $negotiatedLocale = $request->getPreferredLanguage($candidateLocaleCodes);

            if (is_string($negotiatedLocale) && self::isSupported($negotiatedLocale)) {
                return $negotiatedLocale;
            }
        }

        return (string) config('app.locale');
    }

    /**
     * Build the URL of the current page in another language, keeping the
     * path and query string (e.g. /bn/products/rice?page=2 -> /en/products/rice?page=2).
     */
    public static function switchUrl(string $targetLocaleCode, Request $request): string
    {
        $pathSegments = array_values(array_filter(
            explode('/', $request->path()),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($pathSegments !== [] && self::isSupported($pathSegments[0])) {
            $pathSegments[0] = $targetLocaleCode;
        } else {
            array_unshift($pathSegments, $targetLocaleCode);
        }

        $targetUrl = url(implode('/', $pathSegments));
        $queryString = $request->getQueryString();

        return $queryString !== null && $queryString !== ''
            ? $targetUrl . '?' . $queryString
            : $targetUrl;
    }
}
