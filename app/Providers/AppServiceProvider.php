<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Branch\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        // "Scoped": a fresh BranchContext for every HTTP request and every queued job.
        $this->app->scoped(BranchContext::class);
    }

    public function boot(): void
    {
        // Throw in local/testing when a relation is lazy-loaded inside a loop (N+1 query).
        // Production keeps running normally.
        Model::preventLazyLoading(! $this->app->isProduction());

        // The Super Administrator passes every ability check, including permissions added later.
        // Guests are skipped automatically because the callback requires a User.
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        // One password policy for the whole application. The "uncompromised" check calls an
        // external breach-database API, so it runs in production only.
        Password::defaults(static function (): Password {
            $passwordRule = Password::min(12)->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $passwordRule->uncompromised() : $passwordRule;
        });
    }
}
