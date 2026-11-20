<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\AccountController;
use App\Http\Controllers\Frontend\Auth\EmailVerificationController;
use App\Http\Controllers\Frontend\Auth\LoginController;
use App\Http\Controllers\Frontend\Auth\NewPasswordController;
use App\Http\Controllers\Frontend\Auth\PasswordResetLinkController;
use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\LocaleRedirectController;
use App\Support\Localization;
use Illuminate\Support\Facades\Route;

Route::get('/', LocaleRedirectController::class)->name('frontend.locale-redirect');

// Every storefront page lives under /bn/... or /en/...
Route::prefix('{locale}')
    ->where(['locale' => Localization::routePattern()])
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('frontend.home');

        // Guests only (signed-in users are redirected by the "guest" middleware).
        Route::middleware('guest')->group(function (): void {
            Route::get('login', [LoginController::class, 'create'])->name('login');
            Route::post('login', [LoginController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('login.attempt');

            Route::get('register', [RegisterController::class, 'create'])->name('register');
            Route::post('register', [RegisterController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('register.store');

            Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
            Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('password.email');

            Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
            Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('password.update');
        });

        // Signed-in users. "active" signs out accounts that were suspended meanwhile.
        Route::middleware(['auth', 'active'])->group(function (): void {
            Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
            Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
            Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

            Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

            Route::get('account', AccountController::class)
                ->middleware('verified')
                ->name('customer.account');
        });
    });
