<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\FiscalCalendarService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();

    app(FiscalCalendarService::class)->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    foreach ([['1150', AccountType::Asset, 'inventory'], ['5200', AccountType::Expense, 'inventory_adjustments'], ['3300', AccountType::Equity, 'opening_balance_equity']] as [$code, $type, $key]) {
        Account::factory()->create(['code' => $code, 'type' => $type])->forceFill(['system_key' => $key])->save();
    }
});

function stockManager(): User
{
    return createStaffWithRole(RoleName::InventoryManager, ['has_all_branch_access' => true]);
}

/**
 * @return array<string, mixed>
 */
function adjustmentFormPayload(Warehouse $warehouse, int $variantId, array $overrides = []): array
{
    return array_merge([
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => '2026-03-10',
        'reason' => 'found',
        'notes' => 'Shelf count',
        'lines' => [['variant_id' => $variantId, 'quantity_change' => '5', 'unit_cost' => '20', 'batch_number' => '', 'expiry_date' => '']],
    ], $overrides);
}

it('submits an adjustment as pending through the form', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    $this->actingAs(stockManager())
        ->post('/admin/stock-adjustments', adjustmentFormPayload($warehouse, $variantId))
        ->assertRedirect();

    $adjustment = StockAdjustment::withoutBranchScope()->with('lines')->firstOrFail();

    expect($adjustment->status)->toBe(JournalEntryStatus::Pending);
    expect($adjustment->lines)->toHaveCount(1);
    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
});

it('rejects invalid form data with field errors', function (array $overrides, string $invalidField): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    $this->actingAs(stockManager())
        ->post('/admin/stock-adjustments', adjustmentFormPayload($warehouse, $variantId, $overrides))
        ->assertSessionHasErrors($invalidField);

    expect(StockAdjustment::withoutBranchScope()->count())->toBe(0);
})->with([
    'missing warehouse' => [['warehouse_id' => ''], 'warehouse_id'],
    'unknown warehouse' => [['warehouse_id' => 99999], 'warehouse_id'],
    'missing date' => [['adjustment_date' => ''], 'adjustment_date'],
    'wrong date format' => [['adjustment_date' => '10/03/2026'], 'adjustment_date'],
    'future date' => [['adjustment_date' => '2999-01-01'], 'adjustment_date'],
    'unknown reason' => [['reason' => 'fun'], 'reason'],
    'no lines' => [['lines' => []], 'lines'],
    'unknown product' => [['lines' => [['variant_id' => 99999, 'quantity_change' => '5']]], 'lines.0.variant_id'],
    'text quantity' => [['lines' => [['variant_id' => 1, 'quantity_change' => 'five']]], 'lines.0.quantity_change'],
]);

it('shows a service error for rule violations of the form', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    // "Damaged" can only remove stock.
    $this->actingAs(stockManager())
        ->post('/admin/stock-adjustments', adjustmentFormPayload($warehouse, $variantId, ['reason' => 'damaged']))
        ->assertSessionHas('error');

    expect(StockAdjustment::withoutBranchScope()->count())->toBe(0);
});

it('approves through the page and applies stock and accounts', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $maker = stockManager();
    $checker = createHeadOfficeAdmin();

    $adjustment = app(StockAdjustmentService::class)->create([
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => '2026-03-10',
        'reason' => 'found',
        'notes' => null,
        'lines' => [['variant_id' => $variantId, 'quantity_change' => '5', 'unit_cost' => '20']],
    ], $maker);

    // The creator cannot approve their own document.
    $this->actingAs($maker)->post(route('admin.stock-adjustments.approve', $adjustment))->assertForbidden();

    $this->actingAs($checker)->post(route('admin.stock-adjustments.approve', $adjustment))
        ->assertRedirect(route('admin.stock-adjustments.show', $adjustment))
        ->assertSessionHas('success');

    expect(StockAdjustment::withoutBranchScope()->findOrFail($adjustment->id)->status)->toBe(JournalEntryStatus::Posted);
    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('5.0000');

    $this->actingAs($checker)->get(route('admin.stock-adjustments.show', $adjustment))
        ->assertOk()
        ->assertSee($adjustment->adjustment_number);
});

it('rejects through the page only with a reason', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    $adjustment = app(StockAdjustmentService::class)->create([
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => '2026-03-10',
        'reason' => 'found',
        'notes' => null,
        'lines' => [['variant_id' => $variantId, 'quantity_change' => '5', 'unit_cost' => '20']],
    ], stockManager());
    $checker = createHeadOfficeAdmin();

    $this->actingAs($checker)->post(route('admin.stock-adjustments.reject', $adjustment), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($checker)->post(route('admin.stock-adjustments.reject', $adjustment), ['reason' => 'Not verified'])
        ->assertRedirect(route('admin.stock-adjustments.show', $adjustment));

    expect(StockAdjustment::withoutBranchScope()->findOrFail($adjustment->id)->status)->toBe(JournalEntryStatus::Rejected);
});

it('offers no way to edit or delete adjustments', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $adjustment = app(StockAdjustmentService::class)->create([
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => '2026-03-10',
        'reason' => 'found',
        'notes' => null,
        'lines' => [['variant_id' => $variantId, 'quantity_change' => '5', 'unit_cost' => '20']],
    ], stockManager());

    $this->actingAs(createHeadOfficeAdmin())->delete('/admin/stock-adjustments/' . $adjustment->id)->assertStatus(405);
    $this->actingAs(createHeadOfficeAdmin())->put('/admin/stock-adjustments/' . $adjustment->id, [])->assertStatus(405);
});

it('lists and filters adjustments', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $service = app(StockAdjustmentService::class);
    $maker = stockManager();
    $line = [['variant_id' => $variantId, 'quantity_change' => '5', 'unit_cost' => '20']];

    $found = $service->create(['warehouse_id' => $warehouse->id, 'adjustment_date' => '2026-03-10', 'reason' => 'found', 'lines' => $line], $maker);
    $other = $service->create(['warehouse_id' => $warehouse->id, 'adjustment_date' => '2026-05-10', 'reason' => 'other', 'lines' => $line], $maker);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->get('/admin/stock-adjustments')->assertOk()
        ->assertViewHas('adjustments', fn ($page): bool => $page->pluck('id')->all() === [$other->id, $found->id]);

    $this->actingAs($admin)->get('/admin/stock-adjustments?reason=found')
        ->assertViewHas('adjustments', fn ($page): bool => $page->pluck('id')->all() === [$found->id]);

    $this->actingAs($admin)->get('/admin/stock-adjustments?from=2026-05-01')
        ->assertViewHas('adjustments', fn ($page): bool => $page->pluck('id')->all() === [$other->id]);

    $this->actingAs($admin)->get('/admin/stock-adjustments?search=' . $found->adjustment_number)
        ->assertViewHas('adjustments', fn ($page): bool => $page->pluck('id')->all() === [$found->id]);
});

it('keeps adjustments of other branches away from branch-bound staff', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ownWarehouse = Warehouse::factory()->create(['branch_id' => $ownBranch->id]);
    $otherWarehouse = Warehouse::factory()->create(['branch_id' => $otherBranch->id]);
    $variantId = Product::factory()->create()->defaultVariant->id;
    $service = app(StockAdjustmentService::class);
    $maker = stockManager();
    $line = [['variant_id' => $variantId, 'quantity_change' => '1', 'unit_cost' => '5']];

    $own = $service->create(['warehouse_id' => $ownWarehouse->id, 'adjustment_date' => '2026-03-10', 'reason' => 'found', 'lines' => $line], $maker);
    $other = $service->create(['warehouse_id' => $otherWarehouse->id, 'adjustment_date' => '2026-03-10', 'reason' => 'found', 'lines' => $line], $maker);

    $inventoryClerk = createStaffWithRole(RoleName::InventoryManager);
    $inventoryClerk->branches()->attach($ownBranch);

    $this->actingAs($inventoryClerk)->get('/admin/stock-adjustments')
        ->assertOk()
        ->assertViewHas('adjustments', fn ($page): bool => $page->pluck('id')->all() === [$own->id]);

    $this->actingAs($inventoryClerk)->get(route('admin.stock-adjustments.show', $other))->assertNotFound();

    // They cannot create an adjustment for the other branch's warehouse either.
    $this->actingAs($inventoryClerk)
        ->post('/admin/stock-adjustments', adjustmentFormPayload($otherWarehouse, $variantId))
        ->assertSessionHasErrors('warehouse_id');
});

it('follows the permissions of each role', function (): void {
    $warehouse = Warehouse::factory()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $adjustment = app(StockAdjustmentService::class)->create([
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => '2026-03-10',
        'reason' => 'found',
        'notes' => null,
        'lines' => [['variant_id' => $variantId, 'quantity_change' => '1', 'unit_cost' => '5']],
    ], stockManager());

    foreach ([RoleName::Cashier, RoleName::HrManager] as $roleName) {
        $this->actingAs(createStaffWithRole($roleName))->get('/admin/stock-adjustments')->assertForbidden();
    }

    $inventoryManager = stockManager();

    $this->actingAs($inventoryManager)->get('/admin/stock-adjustments')->assertOk();
    $this->actingAs($inventoryManager)->get('/admin/stock-adjustments/create')->assertOk();
    // Inventory managers prepare documents; approving is for others.
    $this->actingAs($inventoryManager)->post(route('admin.stock-adjustments.approve', $adjustment))->assertForbidden();
});

it('searches variants by name, SKU and barcode and reports the stock on hand', function (): void {
    $warehouse = Warehouse::factory()->create();
    $rice = Product::factory()->create(['name' => ['en' => 'Miniket Rice', 'bn' => 'মিনিকেট চাল']]);
    $rice->defaultVariant->update(['sku' => 'RICE-5KG']);
    ProductBarcode::query()->create(['variant_id' => $rice->defaultVariant->id, 'barcode' => '4006381333931', 'type' => 'ean13', 'is_primary' => true]);
    Product::factory()->create(['name' => ['en' => 'Sugar']]);

    app(StockService::class)->apply(new StockChange($warehouse->id, $rice->defaultVariant->id, StockMovementType::PurchaseReceipt, '12', unitCost: '10'));

    $admin = createHeadOfficeAdmin();

    foreach (['Miniket', 'মিনিকেট', 'RICE-5', '40063813'] as $term) {
        $response = $this->actingAs($admin)
            ->getJson('/admin/variants/search?q=' . urlencode($term) . '&warehouse_id=' . $warehouse->id)
            ->assertOk();

        expect($response->json())->toHaveCount(1);
        expect($response->json('0.id'))->toEqual($rice->defaultVariant->id);
        expect($response->json('0.on_hand'))->toBe('12.0000');
        expect($response->json('0.sku'))->toBe('RICE-5KG');
    }

    // Too short, wildcards typed by the user, and unknown words match nothing.
    expect($this->actingAs($admin)->getJson('/admin/variants/search?q=a')->json())->toBe([]);
    expect($this->actingAs($admin)->getJson('/admin/variants/search?q=' . urlencode('%%'))->json())->toBe([]);
    expect($this->actingAs($admin)->getJson('/admin/variants/search?q=zzzzzz')->json())->toBe([]);
});

it('limits the variant search to permitted staff', function (): void {
    Product::factory()->create(['name' => ['en' => 'Searchable thing']]);

    $this->getJson('/admin/variants/search?q=Searchable')->assertUnauthorized();
    $this->actingAs(createStaffWithRole(RoleName::HrManager))->getJson('/admin/variants/search?q=Searchable')->assertForbidden();
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->getJson('/admin/variants/search?q=Searchable')->assertOk();
});
