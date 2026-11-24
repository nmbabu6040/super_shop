<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActiveBranchController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaxRateController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Support\Settings\SettingsSchema;
use Illuminate\Support\Facades\Route;

/*
 | Admin area: /admin/... (route names start with "admin."). Registered in bootstrap/app.php.
 | Staff only: authenticated, email verified, account active, and holding a staff role.
 | Each action is additionally authorized by a policy (permission + branch access).
 */

Route::middleware(['auth', 'verified', 'active', 'staff'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('locale', LocaleController::class)->name('locale.update');
    Route::post('active-branch', ActiveBranchController::class)->name('active-branch.update');

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('feed', [NotificationController::class, 'feed'])->name('feed');
        Route::get('preferences', [NotificationController::class, 'preferences'])->name('preferences');
        Route::put('preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    Route::resource('branches', BranchController::class)->except(['show']);
    Route::post('branches/{branch}/restore', [BranchController::class, 'restore'])
        ->withTrashed()
        ->name('branches.restore');

    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::post('warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])
        ->withTrashed()
        ->name('warehouses.restore');

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('categories.restore');

    Route::resource('brands', BrandController::class)->except(['show']);
    Route::post('brands/{brand}/restore', [BrandController::class, 'restore'])
        ->withTrashed()
        ->name('brands.restore');

    Route::resource('units', UnitController::class)->except(['show']);

    Route::resource('tax-rates', TaxRateController::class)
        ->parameters(['tax-rates' => 'tax_rate'])
        ->except(['show']);

    Route::resource('products', ProductController::class)->except(['show']);
    Route::post('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation');
    Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');

    Route::resource('roles', RoleController::class)->except(['show']);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('settings/{group}', [SettingController::class, 'edit'])
        ->whereIn('group', SettingsSchema::groupKeys())
        ->name('settings.edit');
    Route::put('settings/{group}', [SettingController::class, 'update'])
        ->whereIn('group', SettingsSchema::groupKeys())
        ->name('settings.update');
});
