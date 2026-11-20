<?php

declare(strict_types=1);

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SetLocale;
use App\Support\Auth\PostLoginRedirector;
use App\Support\Localization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'staff' => EnsureUserIsStaff::class,
        ]);

        // Guests who hit a protected page are sent to the login page in the language of the URL.
        // (The "auth" middleware runs before SetLocale, so the locale is read from the request itself.)
        $middleware->redirectGuestsTo(
            static fn (Request $request): string => route('login', [
                'locale' => Localization::currentLocaleFor($request),
            ])
        );

        // Signed-in users who open login/register are sent to their own landing page.
        $middleware->redirectUsersTo(
            static fn (Request $request): string => app(PostLoginRedirector::class)->urlFor($request->user())
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business rule violations: show the message on the form (or 422 JSON for API clients).
        $exceptions->render(function (BusinessRuleException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->back()->with('error', $exception->getMessage());
        });
    })
    ->create();
