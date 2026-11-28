<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockReportService;
use App\Services\Inventory\StockService;
use App\Services\Settings\SettingsService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

function moveStock(Warehouse $warehouse, int $variantId, StockMovementType $type, string $quantity, ?string $cost = null, ?int $batchId = null): void
{
    app(StockService::class)->apply(new StockChange($warehouse->id, $variantId, $type, $quantity, $batchId, $cost));
}

/**
 * A staff user holding only the given permissions, optionally limited to some branches.
 *
 * @param  list<string>  $permissions
 */
function userWithPermissions(array $permissions, Branch ...$branches): User
{
    $role = Role::firstOrCreate(['name' => 'custom-' . md5(implode(',', $permissions)), 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role->name);

    if ($branches !== []) {
        $user->branches()->attach(array_map(static fn (Branch $branch): int => $branch->id, $branches));
    }

    return $user;
}

/**
 * Rice (reorder level 10) with 8 in branch A and 20 in branch B, sugar (reorder level 5) sold out
 * in branch A, and flour (no reorder level) with plenty in branch A.
 *
 * @return array<string, mixed>
 */
function stockReportWorld(): array
{
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);

    $rice = Product::factory()->create(['name' => ['en' => 'Miniket Rice', 'bn' => 'মিনিকেট চাল'], 'reorder_level' => '10']);
    $rice->defaultVariant->update(['sku' => 'RICE-1']);
    ProductBarcode::query()->create(['variant_id' => $rice->defaultVariant->id, 'barcode' => '4006381333931', 'type' => 'ean13', 'is_primary' => true]);
    $sugar = Product::factory()->create(['name' => ['en' => 'Sugar'], 'reorder_level' => '5']);
    $sugar->defaultVariant->update(['sku' => 'SUGAR-1']);
    $flour = Product::factory()->create(['name' => ['en' => 'Flour'], 'reorder_level' => '0']);
    $flour->defaultVariant->update(['sku' => 'FLOUR-1']);

    moveStock($warehouseA, $rice->defaultVariant->id, StockMovementType::PurchaseReceipt, '8', '50');
    moveStock($warehouseB, $rice->defaultVariant->id, StockMovementType::PurchaseReceipt, '20', '50');
    moveStock($warehouseA, $sugar->defaultVariant->id, StockMovementType::PurchaseReceipt, '3', '40');
    moveStock($warehouseA, $sugar->defaultVariant->id, StockMovementType::Sale, '-3');
    moveStock($warehouseA, $flour->defaultVariant->id, StockMovementType::PurchaseReceipt, '50', '30');

    return compact('branchA', 'branchB', 'warehouseA', 'warehouseB', 'rice', 'sugar', 'flour');
}

/**
 * "variantId@warehouseId" => on hand, for easy comparison.
 *
 * @return array<string, string>
 */
function summaryRowsOf($response): array
{
    $rows = [];

    foreach ($response->viewData('rows') as $row) {
        $rows[$row->variant_id . '@' . $row->warehouse_id] = bcadd((string) $row->on_hand, '0', 4);
    }

    ksort($rows);

    return $rows;
}

it('classifies the stock status', function (string $onHand, string $reorderLevel, string $expected): void {
    expect(StockReportService::statusOf($onHand, $reorderLevel))->toBe($expected);
})->with([
    ['0', '10', 'out'],
    ['-2', '10', 'out'],
    ['8', '10', 'low'],
    ['10', '10', 'low'],
    ['10.0001', '10', 'ok'],
    ['3', '0', 'ok'],
]);

it('lists the stock of every variant and warehouse with the batches added up', function (): void {
    $world = stockReportWorld();

    // Batches of one variant in one warehouse count as one row.
    $tracked = Product::factory()->create(['track_batches' => true, 'reorder_level' => '0']);
    $batches = app(BatchService::class);
    moveStock($world['warehouseA'], $tracked->defaultVariant->id, StockMovementType::PurchaseReceipt, '4', '10', $batches->findOrCreate($tracked->defaultVariant->id, 'B1')->id);
    moveStock($world['warehouseA'], $tracked->defaultVariant->id, StockMovementType::PurchaseReceipt, '6', '10', $batches->findOrCreate($tracked->defaultVariant->id, 'B2')->id);

    $response = $this->actingAs(createHeadOfficeAdmin())->get('/admin/stock')->assertOk();
    $rows = summaryRowsOf($response);

    expect($rows)->toHaveCount(5);
    expect($rows[$world['rice']->defaultVariant->id . '@' . $world['warehouseA']->id])->toBe('8.0000');
    expect($rows[$world['rice']->defaultVariant->id . '@' . $world['warehouseB']->id])->toBe('20.0000');
    expect($rows[$tracked->defaultVariant->id . '@' . $world['warehouseA']->id])->toBe('10.0000');
});

it('filters by warehouse, search and status', function (): void {
    $world = stockReportWorld();
    $admin = createHeadOfficeAdmin();
    $riceA = $world['rice']->defaultVariant->id . '@' . $world['warehouseA']->id;
    $riceB = $world['rice']->defaultVariant->id . '@' . $world['warehouseB']->id;
    $sugarA = $world['sugar']->defaultVariant->id . '@' . $world['warehouseA']->id;
    $flourA = $world['flour']->defaultVariant->id . '@' . $world['warehouseA']->id;

    expect(array_keys(summaryRowsOf($this->actingAs($admin)->get('/admin/stock?warehouse_id=' . $world['warehouseB']->id))))->toBe([$riceB]);

    foreach (['Miniket', 'মিনিকেট', 'RICE-1', '40063813'] as $term) {
        expect(array_keys(summaryRowsOf($this->actingAs($admin)->get('/admin/stock?search=' . urlencode($term)))))
            ->toEqualCanonicalizing([$riceA, $riceB]);
    }

    expect(array_keys(summaryRowsOf($this->actingAs($admin)->get('/admin/stock?status=low'))))->toBe([$riceA]);
    expect(array_keys(summaryRowsOf($this->actingAs($admin)->get('/admin/stock?status=out'))))->toBe([$sugarA]);
    expect(array_keys(summaryRowsOf($this->actingAs($admin)->get('/admin/stock?status=ok'))))->toEqualCanonicalizing([$riceB, $flourA]);

    $this->actingAs($admin)->get('/admin/stock?status=weird')->assertSessionHasErrors('status');
});

it('shows branch-bound staff only their own stock and shows costs only with inventory.manage', function (): void {
    $world = stockReportWorld();

    $viewer = userWithPermissions(['inventory.view'], $world['branchA']);
    $response = $this->actingAs($viewer)->get('/admin/stock')->assertOk()->assertViewHas('canSeeCosts', false);

    expect(array_unique(array_map(fn ($key): string => explode('@', $key)[1], array_keys(summaryRowsOf($response)))))
        ->toBe([(string) $world['warehouseA']->id]);

    $manager = userWithPermissions(['inventory.view', 'inventory.manage'], $world['branchA']);

    $this->actingAs($manager)->get('/admin/stock')->assertViewHas('canSeeCosts', true);
});

it('follows the permissions of each role', function (): void {
    stockReportWorld();

    $this->actingAs(userWithPermissions(['products.view']))->get('/admin/stock')->assertForbidden();
    $this->actingAs(userWithPermissions(['products.view']))->get('/admin/stock/batches')->assertForbidden();
    $this->actingAs(userWithPermissions(['products.view']))->get('/admin/stock/movements')->assertForbidden();

    $viewer = userWithPermissions(['inventory.view']);

    $this->actingAs($viewer)->get('/admin/stock')->assertOk();
    $this->actingAs($viewer)->get('/admin/stock/export')->assertForbidden();
});

it('exports the stock as CSV, with costs only for those who may see them', function (): void {
    stockReportWorld();

    $manager = userWithPermissions(['inventory.view', 'inventory.export', 'inventory.manage']);
    $content = $this->actingAs($manager)->get('/admin/stock/export')->assertOk()->streamedContent();

    expect($content)->toContain('RICE-1')->toContain('Average cost');

    $exportOnly = userWithPermissions(['inventory.view', 'inventory.export']);
    $content = $this->actingAs($exportOnly)->get('/admin/stock/export')->assertOk()->streamedContent();

    expect($content)->toContain('RICE-1')->not->toContain('Average cost');
});

it('lists batches by expiry and filters expired, expiring soon and fresh ones', function (): void {
    $warehouse = Warehouse::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $expiringProduct = Product::factory()->create(['track_batches' => true, 'track_expiry' => true]);
    $plainProduct = Product::factory()->create(['track_batches' => true, 'track_expiry' => false]);
    $batches = app(BatchService::class);
    $today = CarbonImmutable::today();

    $short = $batches->findOrCreate($expiringProduct->defaultVariant->id, 'SHORT', $today->addDays(5)->format('Y-m-d'));
    $medium = $batches->findOrCreate($expiringProduct->defaultVariant->id, 'MEDIUM', $today->addDays(100)->format('Y-m-d'));
    $none = $batches->findOrCreate($plainProduct->defaultVariant->id, 'NODATE');

    moveStock($warehouse, $expiringProduct->defaultVariant->id, StockMovementType::PurchaseReceipt, '1', '5', $short->id);
    moveStock($warehouse, $expiringProduct->defaultVariant->id, StockMovementType::PurchaseReceipt, '1', '5', $medium->id);
    moveStock($warehouse, $plainProduct->defaultVariant->id, StockMovementType::PurchaseReceipt, '1', '5', $none->id);

    // Ten days later "SHORT" has expired; "soon" means within 120 days.
    $this->travelTo(CarbonImmutable::now()->addDays(10));
    app(SettingsService::class)->set('inventory', 'expiry_alert_days', 120);

    $admin = createHeadOfficeAdmin();
    $batchIds = fn (string $query): array => $this->actingAs($admin)->get('/admin/stock/batches' . $query)
        ->viewData('levels')->pluck('batch_id')->all();

    // Earliest expiry first, batches without a date last.
    expect($batchIds(''))->toBe([$short->id, $medium->id, $none->id]);
    expect($batchIds('?expiry=expired'))->toBe([$short->id]);
    expect($batchIds('?expiry=soon'))->toBe([$medium->id]);
    expect($batchIds('?expiry=ok'))->toBe([$none->id]);
});

it('shows the stock ledger newest first with filters', function (): void {
    $world = stockReportWorld();
    $admin = createHeadOfficeAdmin();
    $ids = fn (string $query): array => $this->actingAs($admin)->get('/admin/stock/movements' . $query)
        ->viewData('movements')->map(fn ($movement): string => $movement->movement_type->value . '@' . $movement->warehouse_id)->all();

    $all = $ids('');

    expect($all)->toHaveCount(5);
    expect($all[0])->toBe('purchase_receipt@' . $world['warehouseA']->id);
    expect($all[1])->toBe('sale@' . $world['warehouseA']->id);

    expect($ids('?type=sale'))->toBe(['sale@' . $world['warehouseA']->id]);
    expect($ids('?warehouse_id=' . $world['warehouseB']->id))->toBe(['purchase_receipt@' . $world['warehouseB']->id]);
    expect($ids('?search=SUGAR-1'))->toHaveCount(2);
    expect($ids('?from=2999-01-01'))->toBe([]);

    $this->actingAs($admin)->get('/admin/stock/movements?from=2026-05-01&to=2026-04-01')->assertSessionHasErrors('to');
});

it('keeps the ledger of other branches away from branch-bound staff', function (): void {
    $world = stockReportWorld();

    $viewer = userWithPermissions(['inventory.view'], $world['branchB']);
    $warehouseIds = $this->actingAs($viewer)->get('/admin/stock/movements')->assertOk()
        ->viewData('movements')->pluck('warehouse_id')->unique()->all();

    expect($warehouseIds)->toBe([$world['warehouseB']->id]);
});
