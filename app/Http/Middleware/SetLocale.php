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
 * Routes without a locale prefix (admin) use the remembered or default language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeLocale = $route?->parameter('locale');

        if (is_string($routeLocale) && Localization::isSupported($routeLocale)) {
            $localeCode = $routeLocale;
            // Remember the visitor's choice for the next visit to "/".
            $request->session()->put(Localization::sessionKey(), $localeCode);
            // The locale is fully handled here; controllers must not receive it as an argument.
            $route->forgetParameter('locale');
        } else {
            $localeCode = Localization::localeForUnprefixedRoute($request);
        }

        app()->setLocale($localeCode);
        URL::defaults(['locale' => $localeCode]);

        return $next($request);
    }
}
