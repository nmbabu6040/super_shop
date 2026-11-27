<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockCost;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockService;
use App\Services\Settings\SettingsService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $productAttributes
 * @return array{branch: Branch, warehouse: Warehouse, product: Product, variant: App\Models\ProductVariant}
 */
function stockFixture(array $productAttributes = []): array
{
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create($productAttributes);

    return ['branch' => $branch, 'warehouse' => $warehouse, 'product' => $product, 'variant' => $product->defaultVariant];
}

/**
 * @param  array{branch: Branch, warehouse: Warehouse, product: Product, variant: App\Models\ProductVariant}  $fixture
 */
function receiveStock(array $fixture, string $quantity, string $unitCost, ?int $batchId = null, ?Warehouse $warehouse = null): StockMovement
{
    return app(StockService::class)->apply(new StockChange(
        ($warehouse ?? $fixture['warehouse'])->id,
        $fixture['variant']->id,
        StockMovementType::PurchaseReceipt,
        $quantity,
        $batchId,
        $unitCost,
    ));
}

/**
 * @param  array{branch: Branch, warehouse: Warehouse, product: Product, variant: App\Models\ProductVariant}  $fixture
 */
function issueStock(array $fixture, string $quantity, ?int $batchId = null, StockMovementType $type = StockMovementType::Sale): StockMovement
{
    return app(StockService::class)->apply(new StockChange(
        $fixture['warehouse']->id,
        $fixture['variant']->id,
        $type,
        '-' . ltrim($quantity, '-'),
        $batchId,
    ));
}

/**
 * Builds the invalid change of a named scenario (datasets hold names, not closures).
 *
 * @param  array{branch: Branch, warehouse: Warehouse, product: Product, variant: App\Models\ProductVariant}  $fixture
 */
function invalidStockChange(string $scenario, array $fixture): StockChange
{
    $warehouseId = $fixture['warehouse']->id;
    $variantId = $fixture['variant']->id;

    return match ($scenario) {
        'zero quantity' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, '0', unitCost: '10'),
        'too many decimals' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, '1.00001', unitCost: '10'),
        'text quantity' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, 'five', unitCost: '10'),
        'a sale with a positive quantity' => new StockChange($warehouseId, $variantId, StockMovementType::Sale, '5'),
        'a receipt with a negative quantity' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, '-5', unitCost: '10'),
        'a receipt without a cost' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, '5'),
        'a receipt with a negative cost' => new StockChange($warehouseId, $variantId, StockMovementType::PurchaseReceipt, '5', unitCost: '-1'),
        'an inactive warehouse' => (function () use ($fixture, $variantId): StockChange {
            $fixture['warehouse']->update(['is_active' => false]);

            return new StockChange($fixture['warehouse']->id, $variantId, StockMovementType::PurchaseReceipt, '5', unitCost: '10');
        })(),
        'an unknown warehouse' => new StockChange(999999, $variantId, StockMovementType::PurchaseReceipt, '5', unitCost: '10'),
        'an unknown variant' => new StockChange($warehouseId, 999999, StockMovementType::PurchaseReceipt, '5', unitCost: '10'),
        'a deleted variant' => (function () use ($fixture, $warehouseId): StockChange {
            $fixture['variant']->delete();

            return new StockChange($warehouseId, $fixture['variant']->id, StockMovementType::PurchaseReceipt, '5', unitCost: '10');
        })(),
    };
}

it('receives stock into a level, a cost row and an immutable movement', function (): void {
    $fixture = stockFixture();

    $movement = receiveStock($fixture, '10', '100');

    expect($movement->quantity)->toBe('10.0000');
    expect($movement->unit_cost)->toBe('100.0000');
    expect($movement->total_cost)->toBe('1000.00');
    expect($movement->balance_after)->toBe('10.0000');
    expect($movement->movement_type)->toBe(StockMovementType::PurchaseReceipt);
    expect($movement->branch_id)->toEqual($fixture['branch']->id);

    $level = StockLevel::withoutBranchScope()->firstOrFail();

    expect($level->quantity)->toBe('10.0000');
    expect($level->batch_key)->toEqual(0);
    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('100.0000');
});

it('keeps a weighted-average cost and issues stock at that average', function (): void {
    $fixture = stockFixture();

    receiveStock($fixture, '10', '100');
    receiveStock($fixture, '10', '200');

    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('150.0000');

    $issue = issueStock($fixture, '5');

    expect($issue->quantity)->toBe('-5.0000');
    expect($issue->unit_cost)->toBe('150.0000');
    expect($issue->total_cost)->toBe('-750.00');
    expect($issue->balance_after)->toBe('15.0000');

    // Taking stock out does not move the average; adding more does.
    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('150.0000');

    receiveStock($fixture, '15', '250');

    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('200.0000');
});

it('rounds movement values to two decimals and costs to four', function (): void {
    $fixture = stockFixture();

    $movement = receiveStock($fixture, '3', '10.3333');

    // 3 x 10.3333 = 30.9999 -> 31.00
    expect($movement->total_cost)->toBe('31.00');

    receiveStock($fixture, '1', '10');

    // (3 x 10.3333 + 1 x 10) / 4 = 10.249975 -> 10.2500
    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('10.2500');
});

it('keeps the cost of each warehouse apart', function (): void {
    $fixture = stockFixture();
    $otherWarehouse = Warehouse::factory()->create(['branch_id' => $fixture['branch']->id]);

    receiveStock($fixture, '10', '100');
    receiveStock($fixture, '10', '300', null, $otherWarehouse);

    $costs = StockCost::query()->pluck('average_cost', 'warehouse_id');

    expect($costs[$fixture['warehouse']->id])->toBe('100.0000');
    expect($costs[$otherWarehouse->id])->toBe('300.0000');
});

it('refuses to go below zero and stores nothing for the refused change', function (): void {
    $fixture = stockFixture();
    receiveStock($fixture, '5', '100');

    expect(fn () => issueStock($fixture, '6'))->toThrow(BusinessRuleException::class);

    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('5.0000');
    expect(StockMovement::withoutBranchScope()->count())->toBe(1);
});

it('allows negative stock only for non-batch products when the setting is on', function (): void {
    $fixture = stockFixture();

    expect(fn () => issueStock($fixture, '5'))->toThrow(BusinessRuleException::class);

    app(SettingsService::class)->set('inventory', 'allow_negative_stock', true);

    $issue = issueStock($fixture, '5');

    expect($issue->balance_after)->toBe('-5.0000');

    // The average starts again from the incoming cost once stock was negative.
    receiveStock($fixture, '10', '100');

    expect(StockCost::query()->firstOrFail()->average_cost)->toBe('100.0000');
    expect(StockLevel::withoutBranchScope()->firstOrFail()->quantity)->toBe('5.0000');
});

it('never lets a batch-tracked product go negative, even when the setting is on', function (): void {
    app(SettingsService::class)->set('inventory', 'allow_negative_stock', true);

    $fixture = stockFixture(['track_batches' => true]);
    $batch = app(BatchService::class)->findOrCreate($fixture['variant']->id, 'B-1');

    expect(fn () => issueStock($fixture, '1', $batch->id))->toThrow(BusinessRuleException::class);
});

it('rejects invalid changes and stores nothing', function (string $scenario): void {
    $fixture = stockFixture();

    expect(fn () => app(StockService::class)->apply(invalidStockChange($scenario, $fixture)))
        ->toThrow(BusinessRuleException::class);

    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
    expect(StockMovement::withoutBranchScope()->count())->toBe(0);
    expect(StockCost::query()->count())->toBe(0);
})->with([
    'zero quantity',
    'too many decimals',
    'text quantity',
    'a sale with a positive quantity',
    'a receipt with a negative quantity',
    'a receipt without a cost',
    'a receipt with a negative cost',
    'an inactive warehouse',
    'an unknown warehouse',
    'an unknown variant',
    'a deleted variant',
]);

it('records the document behind a movement', function (): void {
    $fixture = stockFixture();

    $movement = app(StockService::class)->apply(new StockChange(
        $fixture['warehouse']->id,
        $fixture['variant']->id,
        StockMovementType::Opening,
        '4',
        unitCost: '25',
        sourceType: 'stock_adjustment',
        sourceId: 77,
        note: 'First count',
        occurredAt: CarbonImmutable::parse('2026-03-01 09:00:00'),
    ));

    $stored = StockMovement::withoutBranchScope()->findOrFail($movement->id);

    expect($stored->source_type)->toBe('stock_adjustment');
    expect($stored->source_id)->toEqual(77);
    expect($stored->note)->toBe('First count');
    expect($stored->occurred_at->format('Y-m-d H:i'))->toBe('2026-03-01 09:00');
});

it('never changes or deletes a movement', function (): void {
    $fixture = stockFixture();
    $movement = receiveStock($fixture, '10', '100');

    expect(fn () => $movement->update(['note' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('applies several changes together or not at all', function (): void {
    $first = stockFixture();
    $second = stockFixture();
    $service = app(StockService::class);

    expect(fn () => $service->applyMany([
        new StockChange($first['warehouse']->id, $first['variant']->id, StockMovementType::PurchaseReceipt, '10', unitCost: '5'),
        // This one cannot be done: there is no stock to take out.
        new StockChange($second['warehouse']->id, $second['variant']->id, StockMovementType::Sale, '-1'),
    ]))->toThrow(BusinessRuleException::class);

    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
    expect(StockMovement::withoutBranchScope()->count())->toBe(0);

    $movements = $service->applyMany([
        new StockChange($second['warehouse']->id, $second['variant']->id, StockMovementType::PurchaseReceipt, '3', unitCost: '5'),
        new StockChange($first['warehouse']->id, $first['variant']->id, StockMovementType::PurchaseReceipt, '10', unitCost: '5'),
    ]);

    expect($movements)->toHaveCount(2);
    expect(StockMovement::withoutBranchScope()->count())->toBe(2);
});
