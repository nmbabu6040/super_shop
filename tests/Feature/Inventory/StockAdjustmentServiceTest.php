<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Exceptions\BranchAccessDeniedException;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockCost;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Accounting\FiscalCalendarService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

/**
 * The fiscal year 2026 and the three system accounts a stock adjustment posts to.
 *
 * @return array{inventory: Account, adjustments: Account, opening: Account}
 */
function adjustmentLedger(): array
{
    app(FiscalCalendarService::class)->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    $make = static function (string $code, AccountType $type, string $systemKey): Account {
        $account = Account::factory()->create(['code' => $code, 'type' => $type]);
        $account->forceFill(['system_key' => $systemKey])->save();

        return $account;
    };

    return [
        'inventory' => $make('1150', AccountType::Asset, 'inventory'),
        'adjustments' => $make('5200', AccountType::Expense, 'inventory_adjustments'),
        'opening' => $make('3300', AccountType::Equity, 'opening_balance_equity'),
    ];
}

/**
 * @param  array<string, mixed>  $productAttributes
 * @return array{branch: Branch, warehouse: Warehouse, variant: App\Models\ProductVariant}
 */
function adjustmentWorld(array $productAttributes = []): array
{
    $branch = Branch::factory()->create();

    return [
        'branch' => $branch,
        'warehouse' => Warehouse::factory()->create(['branch_id' => $branch->id]),
        'variant' => Product::factory()->create($productAttributes)->defaultVariant,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @return array<string, mixed>
 */
function adjustmentData(Warehouse $warehouse, array $lines, string $reason = 'correction', string $date = '2026-03-10'): array
{
    return [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => $date,
        'reason' => $reason,
        'notes' => 'Test',
        'lines' => $lines,
    ];
}

function stockInto(Warehouse $warehouse, int $variantId, string $quantity, string $cost, ?int $batchId = null): void
{
    app(StockService::class)->apply(new StockChange(
        $warehouse->id,
        $variantId,
        StockMovementType::PurchaseReceipt,
        $quantity,
        $batchId,
        $cost
    ));
}

/**
 * Builds the invalid request of a named scenario (datasets hold names, not closures).
 *
 * @return array<string, mixed>
 */
function invalidAdjustmentData(string $scenario): array
{
    $world = adjustmentWorld();
    $tracked = Product::factory()->create(['track_batches' => true])->defaultVariant;
    $line = ['variant_id' => $world['variant']->id, 'quantity_change' => '5', 'unit_cost' => '10'];

    return match ($scenario) {
        'a loss with the opening stock reason' => adjustmentData($world['warehouse'], [[...$line, 'quantity_change' => '-5']], 'opening_stock'),
        'a gain with the damaged reason' => adjustmentData($world['warehouse'], [$line], 'damaged'),
        'zero quantity' => adjustmentData($world['warehouse'], [[...$line, 'quantity_change' => '0']]),
        'text quantity' => adjustmentData($world['warehouse'], [[...$line, 'quantity_change' => 'five']]),
        'a bad cost' => adjustmentData($world['warehouse'], [[...$line, 'unit_cost' => '-1']]),
        'a batch product without a batch' => adjustmentData($world['warehouse'], [['variant_id' => $tracked->id, 'quantity_change' => '5', 'unit_cost' => '10']]),
        'a plain product with a batch' => adjustmentData($world['warehouse'], [[...$line, 'batch_number' => 'B1']]),
        'a bad batch number' => adjustmentData($world['warehouse'], [['variant_id' => $tracked->id, 'quantity_change' => '5', 'batch_number' => 'bad batch!']]),
        'the same line twice' => adjustmentData($world['warehouse'], [$line, $line]),
        'no lines' => adjustmentData($world['warehouse'], []),
        'an unknown variant' => adjustmentData($world['warehouse'], [[...$line, 'variant_id' => 999999]]),
        'an inactive warehouse' => (function () use ($world, $line): array {
            $world['warehouse']->update(['is_active' => false]);

            return adjustmentData($world['warehouse'], [$line]);
        })(),
        'an unknown warehouse' => [...adjustmentData($world['warehouse'], [$line]), 'warehouse_id' => 999999],
        'a future date' => adjustmentData($world['warehouse'], [$line], 'correction', CarbonImmutable::today()->addDay()->format('Y-m-d')),
        'a bad date' => adjustmentData($world['warehouse'], [$line], 'correction', '2026-02-31'),
        'an unknown reason' => adjustmentData($world['warehouse'], [$line], 'fun'),
    };
}

/**
 * @return array<int, array{debit: string, credit: string}>
 */
function journalTotalsByAccount(JournalEntry $entry): array
{
    $totals = [];

    foreach ($entry->lines as $line) {
        $totals[$line->account_id]['debit'] = bcadd($totals[$line->account_id]['debit'] ?? '0', (string) $line->debit, 2);
        $totals[$line->account_id]['credit'] = bcadd($totals[$line->account_id]['credit'] ?? '0', (string) $line->credit, 2);
    }

    return $totals;
}

it('records a pending adjustment without touching stock or accounts', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld(['track_batches' => true]);
    $maker = createStaffWithRole(RoleName::InventoryManager);

    $adjustment = app(StockAdjustmentService::class)->create(adjustmentData($world['warehouse'], [[
        'variant_id' => $world['variant']->id,
        'quantity_change' => '5',
        'batch_number' => ' lot-1 ',
        'expiry_date' => '2030-12-31',
        'unit_cost' => '10',
    ]]), $maker);

    expect($adjustment->status)->toBe(JournalEntryStatus::Pending);
    expect($adjustment->adjustment_number)->toMatch('/^ADJ-.+-2026-000001$/');
    expect($adjustment->created_by)->toEqual($maker->id);
    expect($adjustment->branch_id)->toEqual($world['branch']->id);
    expect($adjustment->lines)->toHaveCount(1);
    expect($adjustment->lines[0]->batch_number)->toBe('LOT-1');
    expect($adjustment->lines[0]->quantity_change)->toBe('5.0000');

    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
    expect(StockBatch::query()->count())->toBe(0);
    expect(JournalEntry::withoutBranchScope()->count())->toBe(0);
});

it('rejects invalid adjustments and stores nothing', function (string $scenario): void {
    adjustmentLedger();
    $data = invalidAdjustmentData($scenario);

    expect(fn () => app(StockAdjustmentService::class)->create($data, createStaffWithRole(RoleName::InventoryManager)))
        ->toThrow(BusinessRuleException::class);

    expect(StockAdjustment::withoutBranchScope()->count())->toBe(0);
})->with([
    'a loss with the opening stock reason',
    'a gain with the damaged reason',
    'zero quantity',
    'text quantity',
    'a bad cost',
    'a batch product without a batch',
    'a plain product with a batch',
    'a bad batch number',
    'the same line twice',
    'no lines',
    'an unknown variant',
    'an inactive warehouse',
    'an unknown warehouse',
    'a future date',
    'a bad date',
    'an unknown reason',
]);

it('numbers adjustments per branch and year', function (): void {
    adjustmentLedger();
    $branch = Branch::factory()->create(['code' => 'BR-9']);
    $branchWarehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);
    $central = Warehouse::factory()->central()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $service = app(StockAdjustmentService::class);
    $maker = createStaffWithRole(RoleName::InventoryManager);
    $line = ['variant_id' => $variantId, 'quantity_change' => '1', 'unit_cost' => '5'];

    expect($service->create(adjustmentData($branchWarehouse, [$line]), $maker)->adjustment_number)->toBe('ADJ-BR-9-2026-000001');
    expect($service->create(adjustmentData($branchWarehouse, [$line]), $maker)->adjustment_number)->toBe('ADJ-BR-9-2026-000002');
    expect($service->create(adjustmentData($central, [$line]), $maker)->adjustment_number)->toBe('ADJ-HO-2026-000001');
});

it('posts opening stock against opening balance equity', function (): void {
    $accounts = adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);

    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '10', 'unit_cost' => '100'],
    ], 'opening_stock'), createStaffWithRole(RoleName::InventoryManager));

    $approved = $service->approve($adjustment, createStaffWithRole(RoleName::InventoryManager));

    expect($approved->status)->toBe(JournalEntryStatus::Posted);
    expect($approved->total_value)->toBe('1000.00');
    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('10.0000');
    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('100.0000');

    $movement = StockMovement::withoutBranchScope()->firstOrFail();

    expect($movement->movement_type)->toBe(StockMovementType::Opening);
    expect($movement->source_type)->toBe('stock_adjustment');
    expect($movement->source_id)->toEqual($adjustment->id);

    $line = $approved->lines()->firstOrFail();

    expect($line->stock_movement_id)->toEqual($movement->id);
    expect($line->applied_unit_cost)->toBe('100.0000');
    expect($line->total_cost)->toBe('1000.00');

    $entry = JournalEntry::withoutBranchScope()->with('lines')->findOrFail($approved->journal_entry_id);
    $totals = journalTotalsByAccount($entry);

    expect($entry->status)->toBe(JournalEntryStatus::Posted);
    expect($entry->branch_id)->toEqual($world['branch']->id);
    expect($totals[$accounts['inventory']->id])->toBe(['debit' => '1000.00', 'credit' => '0.00']);
    expect($totals[$accounts['opening']->id])->toBe(['debit' => '0.00', 'credit' => '1000.00']);
});

it('writes off damaged stock at the average cost', function (): void {
    $accounts = adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);
    stockInto($world['warehouse'], $world['variant']->id, '10', '100');

    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '-2'],
    ], 'damaged'), createStaffWithRole(RoleName::InventoryManager));

    $approved = $service->approve($adjustment, createStaffWithRole(RoleName::InventoryManager));

    expect($approved->total_value)->toBe('-200.00');
    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('8.0000');
    expect(StockMovement::withoutBranchScope()->latest('id')->firstOrFail()->movement_type)->toBe(StockMovementType::Damage);

    $totals = journalTotalsByAccount(JournalEntry::withoutBranchScope()->with('lines')->findOrFail($approved->journal_entry_id));

    expect($totals[$accounts['adjustments']->id])->toBe(['debit' => '200.00', 'credit' => '0.00']);
    expect($totals[$accounts['inventory']->id])->toBe(['debit' => '0.00', 'credit' => '200.00']);
});

it('posts gains and losses of one document in one balanced entry', function (): void {
    $accounts = adjustmentLedger();
    $world = adjustmentWorld();
    $otherVariant = Product::factory()->create()->defaultVariant;
    $service = app(StockAdjustmentService::class);
    stockInto($world['warehouse'], $otherVariant->id, '4', '10');

    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '5', 'unit_cost' => '50'],
        ['variant_id' => $otherVariant->id, 'quantity_change' => '-1'],
    ], 'correction'), createStaffWithRole(RoleName::InventoryManager));

    $approved = $service->approve($adjustment, createStaffWithRole(RoleName::InventoryManager));
    $entry = JournalEntry::withoutBranchScope()->with('lines')->findOrFail($approved->journal_entry_id);
    $totals = journalTotalsByAccount($entry);

    expect($approved->total_value)->toBe('240.00');
    expect($entry->lines)->toHaveCount(4);
    expect($entry->total_amount)->toBe('260.00');
    expect($totals[$accounts['inventory']->id])->toBe(['debit' => '250.00', 'credit' => '10.00']);
    expect($totals[$accounts['adjustments']->id])->toBe(['debit' => '10.00', 'credit' => '250.00']);
});

it('adds stock at the current average cost when no cost is given, and needs a cost when there is none', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);
    $maker = createStaffWithRole(RoleName::InventoryManager);
    $checker = createStaffWithRole(RoleName::InventoryManager);

    // No stock and no average cost yet: a cost is needed.
    $withoutCost = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '5'],
    ], 'found'), $maker);

    expect(fn () => $service->approve($withoutCost, $checker))->toThrow(BusinessRuleException::class);
    expect(StockAdjustment::withoutBranchScope()->findOrFail($withoutCost->id)->status)->toBe(JournalEntryStatus::Pending);
    expect(StockLevel::withoutBranchScope()->count())->toBe(0);

    // With an average cost the line takes it.
    stockInto($world['warehouse'], $world['variant']->id, '10', '100');

    $approved = $service->approve($withoutCost, $checker);

    expect($approved->total_value)->toBe('500.00');
    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('15.0000');
});

it('creates the batch when stock is added and requires an existing batch to remove stock', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld(['track_batches' => true, 'track_expiry' => true]);
    $service = app(StockAdjustmentService::class);
    $maker = createStaffWithRole(RoleName::InventoryManager);
    $checker = createStaffWithRole(RoleName::InventoryManager);
    $expiry = CarbonImmutable::today()->addDays(90)->format('Y-m-d');

    $service->approve($service->create(adjustmentData($world['warehouse'], [[
        'variant_id' => $world['variant']->id,
        'quantity_change' => '10',
        'batch_number' => 'lot-7',
        'expiry_date' => $expiry,
        'unit_cost' => '20',
    ]], 'found'), $maker), $checker);

    $batch = StockBatch::query()->firstOrFail();

    expect($batch->batch_number)->toBe('LOT-7');
    expect($batch->expiry_date->format('Y-m-d'))->toBe($expiry);
    expect(StockLevel::withoutBranchScope()->where('batch_id', $batch->id)->firstOrFail()->quantity)->toBe('10.0000');

    // Removing stock of the existing batch works ...
    $service->approve($service->create(adjustmentData($world['warehouse'], [[
        'variant_id' => $world['variant']->id,
        'quantity_change' => '-3',
        'batch_number' => 'LOT-7',
    ]], 'damaged'), $maker), $checker);

    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('7.0000');

    // ... but an unknown batch cannot be written off, and creates nothing.
    $unknownBatch = $service->create(adjustmentData($world['warehouse'], [[
        'variant_id' => $world['variant']->id,
        'quantity_change' => '-1',
        'batch_number' => 'NOPE',
    ]], 'lost'), $maker);

    expect(fn () => $service->approve($unknownBatch, $checker))->toThrow(BusinessRuleException::class);
    expect(StockBatch::query()->count())->toBe(1);
});

it('applies everything or nothing when approving', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $emptyVariant = Product::factory()->create()->defaultVariant;
    $service = app(StockAdjustmentService::class);

    // The first line is fine, the second tries to remove stock that does not exist.
    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '5', 'unit_cost' => '10'],
        ['variant_id' => $emptyVariant->id, 'quantity_change' => '-100'],
    ], 'correction'), createStaffWithRole(RoleName::InventoryManager));

    expect(fn () => $service->approve($adjustment, createStaffWithRole(RoleName::InventoryManager)))
        ->toThrow(BusinessRuleException::class);

    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
    expect(StockMovement::withoutBranchScope()->count())->toBe(0);
    expect(JournalEntry::withoutBranchScope()->count())->toBe(0);
    expect(StockAdjustment::withoutBranchScope()->findOrFail($adjustment->id)->status)->toBe(JournalEntryStatus::Pending);
});

it('does not approve into a closed accounting period', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);

    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '5', 'unit_cost' => '10'],
    ], 'found', '2026-03-10'), createStaffWithRole(RoleName::InventoryManager));

    app(FiscalCalendarService::class)->closePeriod(
        AccountingPeriod::query()->where('name', '2026-03')->firstOrFail(),
        createHeadOfficeAdmin()
    );

    expect(fn () => $service->approve($adjustment, createStaffWithRole(RoleName::InventoryManager)))
        ->toThrow(BusinessRuleException::class);

    // The stock change was rolled back together with the failed posting.
    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
    expect(StockAdjustment::withoutBranchScope()->findOrFail($adjustment->id)->status)->toBe(JournalEntryStatus::Pending);
});

it('needs another person to approve, except for a super administrator', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);
    $maker = createStaffWithRole(RoleName::InventoryManager);
    $data = adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '1', 'unit_cost' => '10'],
    ], 'found');

    $adjustment = $service->create($data, $maker);

    expect(fn () => $service->approve($adjustment, $maker))->toThrow(BusinessRuleException::class);
    expect(StockLevel::withoutBranchScope()->count())->toBe(0);

    $owner = createStaffWithRole(RoleName::SuperAdmin);

    expect($service->approve($service->create($data, $owner), $owner)->status)->toBe(JournalEntryStatus::Posted);
});

it('rejects an adjustment with a reason and never applies it', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);
    $checker = createStaffWithRole(RoleName::InventoryManager);

    $adjustment = $service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '1', 'unit_cost' => '10'],
    ], 'found'), createStaffWithRole(RoleName::InventoryManager));

    $rejected = $service->reject($adjustment, $checker, 'Not verified');

    expect($rejected->status)->toBe(JournalEntryStatus::Rejected);
    expect($rejected->rejection_reason)->toBe('Not verified');
    expect(fn () => $service->approve($adjustment, $checker))->toThrow(BusinessRuleException::class);
    expect(fn () => $service->reject($adjustment, $checker, 'Again'))->toThrow(BusinessRuleException::class);
    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
});

it('never changes or deletes a posted adjustment or its requested lines', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $service = app(StockAdjustmentService::class);

    $approved = $service->approve($service->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '3', 'unit_cost' => '10'],
    ], 'found'), createStaffWithRole(RoleName::InventoryManager)), createStaffWithRole(RoleName::InventoryManager));

    $line = $approved->lines()->firstOrFail();

    expect(fn () => $approved->update(['notes' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $approved->delete())->toThrow(LogicException::class);
    expect(fn () => $line->update(['quantity_change' => '99']))->toThrow(LogicException::class);
    expect(fn () => $line->delete())->toThrow(LogicException::class);
});

it('lets branch-bound staff adjust only the stock of their own branch', function (): void {
    adjustmentLedger();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ownWarehouse = Warehouse::factory()->create(['branch_id' => $ownBranch->id]);
    $otherWarehouse = Warehouse::factory()->create(['branch_id' => $otherBranch->id]);
    $central = Warehouse::factory()->central()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;
    $service = app(StockAdjustmentService::class);
    $line = ['variant_id' => $variantId, 'quantity_change' => '1', 'unit_cost' => '10'];

    $staff = createBranchStaff($ownBranch);
    $this->actingAs($staff);

    expect($service->create(adjustmentData($ownWarehouse, [$line]), $staff)->branch_id)->toEqual($ownBranch->id);
    expect(fn () => $service->create(adjustmentData($otherWarehouse, [$line]), $staff))->toThrow(BranchAccessDeniedException::class);
    expect(fn () => $service->create(adjustmentData($central, [$line]), $staff))->toThrow(BranchAccessDeniedException::class);

    // The refused attempts gave their numbers back.
    $this->actingAs(createHeadOfficeAdmin());

    expect($service->create(adjustmentData($otherWarehouse, [$line]), createHeadOfficeAdmin())->adjustment_number)
        ->toEndWith('-2026-000001');
});

it('protects products and warehouses that still hold stock', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();
    $product = $world['variant']->product;
    stockInto($world['warehouse'], $world['variant']->id, '5', '10');

    expect(fn () => $product->delete())->toThrow(BusinessRuleException::class);
    expect(fn () => $world['warehouse']->delete())->toThrow(BusinessRuleException::class);

    // Once the stock is gone both can be deleted.
    app(StockService::class)->apply(new StockChange(
        $world['warehouse']->id,
        $world['variant']->id,
        StockMovementType::Sale,
        '-5'
    ));

    $world['warehouse']->delete();
    $product->delete();

    expect(Warehouse::onlyTrashed()->whereKey($world['warehouse']->id)->exists())->toBeTrue();
    expect(Product::onlyTrashed()->whereKey($product->id)->exists())->toBeTrue();
});

it('protects a warehouse that has adjustments waiting for approval', function (): void {
    adjustmentLedger();
    $world = adjustmentWorld();

    app(StockAdjustmentService::class)->create(adjustmentData($world['warehouse'], [
        ['variant_id' => $world['variant']->id, 'quantity_change' => '1', 'unit_cost' => '10'],
    ], 'found'), createStaffWithRole(RoleName::InventoryManager));

    expect(fn () => $world['warehouse']->delete())->toThrow(BusinessRuleException::class);
});
