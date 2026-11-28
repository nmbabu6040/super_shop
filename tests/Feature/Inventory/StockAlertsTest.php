<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ExpiryAlertNotification;
use App\Notifications\LowStockAlertNotification;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockAlertService;
use App\Services\Inventory\StockService;
use App\Services\Settings\SettingsService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

function alertStock(Warehouse $warehouse, int $variantId, string $quantity, ?int $batchId = null): void
{
    app(StockService::class)->apply(new StockChange($warehouse->id, $variantId, StockMovementType::PurchaseReceipt, $quantity, $batchId, '10'));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function alertViewer(array $attributes = [], ?Branch $branch = null): User
{
    $role = Role::firstOrCreate(['name' => 'stock-viewer', 'guard_name' => 'web']);
    $role->syncPermissions(['inventory.view']);

    $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
    $user->assignRole('stock-viewer');

    if ($branch !== null) {
        $user->branches()->attach($branch);
    }

    return $user;
}

it('counts the products at or below their reorder level per branch', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);

    $low = Product::factory()->create(['reorder_level' => '10']);
    $enough = Product::factory()->create(['reorder_level' => '10']);
    $noLevel = Product::factory()->create(['reorder_level' => '0']);
    $inactive = Product::factory()->create(['reorder_level' => '10', 'is_active' => false]);

    alertStock($warehouseA, $low->defaultVariant->id, '10');
    alertStock($warehouseB, $low->defaultVariant->id, '3');
    alertStock($warehouseA, $enough->defaultVariant->id, '11');
    alertStock($warehouseA, $noLevel->defaultVariant->id, '1');
    alertStock($warehouseA, $inactive->defaultVariant->id, '1');

    $alerts = app(StockAlertService::class);

    expect($alerts->lowStockCount(null))->toBe(2);
    expect($alerts->lowStockCount([$branchA->id]))->toBe(1);
    expect($alerts->lowStockCount([$branchA->id, $branchB->id]))->toBe(2);
    expect($alerts->lowStockCount([]))->toBe(0);
});

it('counts expired and soon-to-expire batches that still hold stock', function (): void {
    $warehouse = Warehouse::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $product = Product::factory()->create(['track_batches' => true, 'track_expiry' => true]);
    $batches = app(BatchService::class);
    $today = CarbonImmutable::today();

    foreach (['SHORT' => 5, 'MEDIUM' => 100, 'EMPTY' => 5] as $number => $days) {
        $batch = $batches->findOrCreate($product->defaultVariant->id, $number, $today->addDays($days)->format('Y-m-d'));
        alertStock($warehouse, $product->defaultVariant->id, '1', $batch->id);
    }

    // The EMPTY batch is sold out, so it needs no alert.
    app(StockService::class)->apply(new StockChange(
        $warehouse->id,
        $product->defaultVariant->id,
        StockMovementType::Sale,
        '-1',
        $batches->findOrCreate($product->defaultVariant->id, 'EMPTY')->id
    ));

    $this->travelTo(CarbonImmutable::now()->addDays(10));

    $alerts = app(StockAlertService::class);

    expect($alerts->expiryCounts(null, 60))->toBe(['expired' => 1, 'soon' => 0]);
    expect($alerts->expiryCounts(null, 120))->toBe(['expired' => 1, 'soon' => 1]);
    expect($alerts->expiryCounts([], 120))->toBe(['expired' => 0, 'soon' => 0]);
});

it('sends each recipient the alerts of their own branches, once a day', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);
    $product = Product::factory()->create(['reorder_level' => '10']);

    alertStock($warehouseA, $product->defaultVariant->id, '2');
    alertStock($warehouseB, $product->defaultVariant->id, '2');

    $branchViewer = alertViewer([], $branchA);
    $headOfficeViewer = alertViewer(['has_all_branch_access' => true]);
    $superAdmin = createStaffWithRole(RoleName::SuperAdmin, ['status' => 'active']);
    $cashierWithoutPermission = createStaffWithRole(RoleName::HrManager, ['status' => 'active']);
    $suspended = alertViewer(['status' => 'suspended']);
    $withoutBranches = alertViewer();

    $this->artisan('inventory:send-alerts')->assertSuccessful();

    $count = fn (User $user): ?string => $user->notifications()
        ->where('type', LowStockAlertNotification::class)->first()?->data['params']['count'] ?? null;

    expect($count($branchViewer))->toBe('1');
    expect($count($headOfficeViewer))->toBe('2');
    expect($count($superAdmin))->toBe('2');
    expect($count($cashierWithoutPermission))->toBeNull();
    expect($count($suspended))->toBeNull();
    expect($count($withoutBranches))->toBeNull();

    // A second run on the same day sends nothing new ...
    $this->artisan('inventory:send-alerts')->assertSuccessful();

    expect($branchViewer->notifications()->where('type', LowStockAlertNotification::class)->count())->toBe(1);

    // ... and the next day it tells them again.
    $this->travelTo(CarbonImmutable::now()->addDay());
    $this->artisan('inventory:send-alerts')->assertSuccessful();

    expect($branchViewer->notifications()->where('type', LowStockAlertNotification::class)->count())->toBe(2);
});

it('sends the expiry alert with the numbers and a link to the right page', function (): void {
    $warehouse = Warehouse::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $product = Product::factory()->create(['track_batches' => true, 'track_expiry' => true]);
    $batches = app(BatchService::class);
    $today = CarbonImmutable::today();

    alertStock($warehouse, $product->defaultVariant->id, '1', $batches->findOrCreate($product->defaultVariant->id, 'SHORT', $today->addDays(5)->format('Y-m-d'))->id);
    alertStock($warehouse, $product->defaultVariant->id, '1', $batches->findOrCreate($product->defaultVariant->id, 'MEDIUM', $today->addDays(40)->format('Y-m-d'))->id);

    $this->travelTo(CarbonImmutable::now()->addDays(10));
    app(SettingsService::class)->set('inventory', 'expiry_alert_days', 60);

    $viewer = alertViewer(['has_all_branch_access' => true]);

    $this->artisan('inventory:send-alerts')->assertSuccessful();

    $notification = $viewer->notifications()->where('type', ExpiryAlertNotification::class)->firstOrFail();

    expect($notification->data['params'])->toBe(['expired' => '1', 'soon' => '1', 'days' => '60']);
    expect($notification->data['route']['name'])->toBe('admin.stock.batches');
    expect($notification->data['route']['parameters'])->toBe(['expiry' => 'expired']);

    // Nothing is low on stock, so no low-stock notification was sent.
    expect($viewer->notifications()->where('type', LowStockAlertNotification::class)->count())->toBe(0);
});

it('sends nothing when nothing needs attention', function (): void {
    $viewer = alertViewer(['has_all_branch_access' => true]);

    $this->artisan('inventory:send-alerts')->assertSuccessful();

    expect($viewer->notifications()->count())->toBe(0);
});
