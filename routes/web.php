<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\LocaleRedirectController;
use App\Support\Localization;
use Illuminate\Support\Facades\Route;

Route::get('/', LocaleRedirectController::class)->name('frontend.locale-redirect');

// Every storefront page lives under /bn/... or /en/...
Route::prefix('{locale}')
    ->where(['locale' => Localization::routePattern()])
    ->name('frontend.')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
    });
