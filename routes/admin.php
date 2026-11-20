<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActiveBranchController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\WarehouseController;
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
});
