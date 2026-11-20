<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActiveBranchController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\SettingController;
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

    Route::resource('branches', BranchController::class)->except(['show']);
    Route::post('branches/{branch}/restore', [BranchController::class, 'restore'])
        ->withTrashed()
        ->name('branches.restore');

    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::post('warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])
        ->withTrashed()
        ->name('warehouses.restore');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation');
    Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('settings/{group}', [SettingController::class, 'edit'])
        ->whereIn('group', SettingsSchema::groupKeys())
        ->name('settings.edit');
    Route::put('settings/{group}', [SettingController::class, 'update'])
        ->whereIn('group', SettingsSchema::groupKeys())
        ->name('settings.update');
});
