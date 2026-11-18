<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language found in the {locale} URL segment to the whole request
 * and makes route() calls automatically include the current locale.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && Localization::isSupported($routeLocale)) {
            $localeCode = $routeLocale;
            // Remember the visitor's choice for the next visit to "/".
            $request->session()->put(Localization::sessionKey(), $localeCode);
        } else {
            $localeCode = (string) config('app.locale');
        }

        app()->setLocale($localeCode);
        URL::defaults(['locale' => $localeCode]);

        return $next($request);
    }
}
