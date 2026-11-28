<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AccountingReportController;
use App\Http\Controllers\Admin\ActiveBranchController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FiscalYearController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockLevelController;
use App\Http\Controllers\Admin\TaxRateController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariantSearchController;
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

    // Variant search for the line editors of stock documents.
    Route::get('variants/search', VariantSearchController::class)
        ->middleware('throttle:60,1')
        ->name('variants.search');

    // Stock overview, batches with expiry dates and the stock ledger (read only).
    Route::get('stock', [StockLevelController::class, 'index'])->name('stock.index');
    Route::get('stock/batches', [StockLevelController::class, 'batches'])->name('stock.batches');
    Route::get('stock/movements', [StockLevelController::class, 'movements'])->name('stock.movements');
    Route::get('stock/export', [StockLevelController::class, 'export'])->name('stock.export');

    // Stock documents are never edited or deleted: created, approved or rejected.
    Route::resource('stock-adjustments', StockAdjustmentController::class)
        ->parameters(['stock-adjustments' => 'stock_adjustment'])
        ->only(['index', 'create', 'store', 'show']);
    Route::post('stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
    Route::post('stock-adjustments/{stock_adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');

    Route::resource('accounts', AccountController::class)->except(['show']);

    Route::resource('fiscal-years', FiscalYearController::class)
        ->parameters(['fiscal-years' => 'fiscal_year'])
        ->only(['index', 'create', 'store', 'destroy']);
    Route::post('fiscal-years/periods/{period}/close', [FiscalYearController::class, 'closePeriod'])->name('fiscal-years.periods.close');
    Route::post('fiscal-years/periods/{period}/reopen', [FiscalYearController::class, 'reopenPeriod'])->name('fiscal-years.periods.reopen');

    // Journal entries are never edited or deleted: only created, approved, rejected or reversed.
    Route::resource('journal-entries', JournalEntryController::class)
        ->parameters(['journal-entries' => 'journal_entry'])
        ->only(['index', 'create', 'store', 'show']);
    Route::post('journal-entries/{journal_entry}/approve', [JournalEntryController::class, 'approve'])->name('journal-entries.approve');
    Route::post('journal-entries/{journal_entry}/reject', [JournalEntryController::class, 'reject'])->name('journal-entries.reject');
    Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');

    Route::get('reports/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('reports/trial-balance/export', [AccountingReportController::class, 'trialBalanceExport'])->name('reports.trial-balance.export');
    Route::get('reports/general-ledger', [AccountingReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('reports/general-ledger/export', [AccountingReportController::class, 'generalLedgerExport'])->name('reports.general-ledger.export');

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
