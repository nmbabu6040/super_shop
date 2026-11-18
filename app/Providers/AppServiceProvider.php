<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        // Throw in local/testing when a relation is lazy-loaded inside a loop (N+1 query).
        // Production keeps running normally.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
