<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockQueryService;
use App\Services\Inventory\StockService;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @return array{warehouse: Warehouse, variant: App\Models\ProductVariant}
 */
function batchFixture(bool $trackExpiry = true): array
{
    $warehouse = Warehouse::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $product = Product::factory()->create(['track_batches' => true, 'track_expiry' => $trackExpiry]);

    return ['warehouse' => $warehouse, 'variant' => $product->defaultVariant];
}

function batchChange(array $fixture, StockMovementType $type, string $quantity, ?int $batchId, ?string $cost = null): StockChange
{
    return new StockChange($fixture['warehouse']->id, $fixture['variant']->id, $type, $quantity, $batchId, $cost);
}

function inDays(int $days): string
{
    return CarbonImmutable::today()->addDays($days)->format('Y-m-d');
}

it('finds or creates a batch and normalises its number', function (): void {
    $fixture = batchFixture();
    $service = app(BatchService::class);

    $created = $service->findOrCreate($fixture['variant']->id, ' lot-001 ', inDays(30));
    $again = $service->findOrCreate($fixture['variant']->id, 'LOT-001', inDays(30));

    expect($created->batch_number)->toBe('LOT-001');
    expect($again->id)->toEqual($created->id);
    expect(StockBatch::query()->count())->toBe(1);

    // The same number on another variant is another batch.
    $other = Product::factory()->create(['track_batches' => true])->defaultVariant;

    expect($service->findOrCreate($other->id, 'LOT-001')->id)->not->toEqual($created->id);
});

it('never silently changes the expiry date of an existing batch', function (): void {
    $fixture = batchFixture();
    $service = app(BatchService::class);

    $service->findOrCreate($fixture['variant']->id, 'LOT-001', inDays(30));

    expect(fn () => $service->findOrCreate($fixture['variant']->id, 'LOT-001', inDays(60)))
        ->toThrow(BusinessRuleException::class);

    // Asking without an expiry date just finds it.
    expect($service->findOrCreate($fixture['variant']->id, 'LOT-001')->expiry_date->format('Y-m-d'))->toBe(inDays(30));
});

it('refuses invalid batch input', function (string $number, ?string $expiry): void {
    $fixture = batchFixture();

    expect(fn () => app(BatchService::class)->findOrCreate($fixture['variant']->id, $number, $expiry))
        ->toThrow(BusinessRuleException::class);

    expect(StockBatch::query()->count())->toBe(0);
})->with([
    'empty number' => ['  ', null],
    'strange characters' => ['LOT 001!', null],
    'too long' => [str_repeat('A', 61), null],
    'wrong date format' => ['LOT-1', '30/12/2030'],
    'impossible date' => ['LOT-1', '2030-02-31'],
]);

it('requires a batch for a batch-tracked product and refuses one otherwise', function (): void {
    $fixture = batchFixture();
    $service = app(StockService::class);

    expect(fn () => $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', null, '10')))
        ->toThrow(BusinessRuleException::class);

    // A product without batch tracking cannot take a batch.
    $plainVariant = Product::factory()->create()->defaultVariant;
    $strayBatch = StockBatch::query()->create(['variant_id' => $plainVariant->id, 'batch_number' => 'STRAY']);

    expect(fn () => $service->apply(new StockChange(
        $fixture['warehouse']->id,
        $plainVariant->id,
        StockMovementType::PurchaseReceipt,
        '5',
        $strayBatch->id,
        '10'
    )))->toThrow(BusinessRuleException::class);

    expect(StockLevel::withoutBranchScope()->count())->toBe(0);
});

it('refuses a batch that belongs to another variant', function (): void {
    $fixture = batchFixture();
    $otherVariant = Product::factory()->create(['track_batches' => true])->defaultVariant;
    $foreignBatch = app(BatchService::class)->findOrCreate($otherVariant->id, 'FOREIGN', inDays(30));

    expect(fn () => app(StockService::class)->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', $foreignBatch->id, '10')))
        ->toThrow(BusinessRuleException::class);
});

it('keeps separate levels for separate batches', function (): void {
    $fixture = batchFixture();
    $service = app(StockService::class);
    $batches = app(BatchService::class);

    $first = $batches->findOrCreate($fixture['variant']->id, 'A', inDays(30));
    $second = $batches->findOrCreate($fixture['variant']->id, 'B', inDays(60));

    $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', $first->id, '10'));
    $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '7', $second->id, '20'));

    expect(StockLevel::withoutBranchScope()->count())->toBe(2);
    expect(app(StockQueryService::class)->onHand($fixture['variant']->id, $fixture['warehouse']->id))->toBe('12.0000');

    // One average cost per warehouse and variant: (5 x 10 + 7 x 20) / 12 = 15.8333
    expect(App\Models\StockCost::query()->firstOrFail()->average_cost)->toBe('15.8333');
});

it('requires an expiry date for expiry-tracked products and refuses to receive expired batches', function (): void {
    $fixture = batchFixture();
    $service = app(StockService::class);

    $withoutExpiry = app(BatchService::class)->findOrCreate($fixture['variant']->id, 'NOEXP');

    expect(fn () => $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', $withoutExpiry->id, '10')))
        ->toThrow(BusinessRuleException::class);

    $expired = StockBatch::query()->create([
        'variant_id' => $fixture['variant']->id,
        'batch_number' => 'OLD',
        'expiry_date' => inDays(-1),
    ]);

    expect(fn () => $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', $expired->id, '10')))
        ->toThrow(BusinessRuleException::class);

    // Without expiry tracking a missing date is fine.
    $plain = batchFixture(false);
    $plainBatch = app(BatchService::class)->findOrCreate($plain['variant']->id, 'NOEXP');

    expect($service->apply(batchChange($plain, StockMovementType::PurchaseReceipt, '5', $plainBatch->id, '10'))->balance_after)->toBe('5.0000');
});

it('blocks selling expired stock but still lets it be written off', function (): void {
    $fixture = batchFixture();
    $service = app(StockService::class);
    $batch = app(BatchService::class)->findOrCreate($fixture['variant']->id, 'SHORT', inDays(2));

    $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '10', $batch->id, '10'));

    // Valid through its expiry date ...
    $this->travelTo(CarbonImmutable::now()->addDays(2));
    expect($service->apply(batchChange($fixture, StockMovementType::Sale, '-1', $batch->id))->balance_after)->toBe('9.0000');

    // ... expired the day after.
    $this->travelTo(CarbonImmutable::now()->addDay());

    expect(fn () => $service->apply(batchChange($fixture, StockMovementType::Sale, '-1', $batch->id)))
        ->toThrow(BusinessRuleException::class);
    expect(fn () => $service->apply(batchChange($fixture, StockMovementType::TransferOut, '-1', $batch->id)))
        ->toThrow(BusinessRuleException::class);

    $writeOff = $service->apply(batchChange($fixture, StockMovementType::Expired, '-9', $batch->id));

    expect($writeOff->balance_after)->toBe('0.0000');
});

it('allocates first-expiry-first-out, puts batches without expiry last and skips expired ones', function (): void {
    $fixture = batchFixture(false);
    $service = app(StockService::class);
    $batches = app(BatchService::class);

    $late = $batches->findOrCreate($fixture['variant']->id, 'LATE', inDays(10));
    $soon = $batches->findOrCreate($fixture['variant']->id, 'SOON', inDays(5));
    $never = $batches->findOrCreate($fixture['variant']->id, 'NEVER');
    $shortLived = $batches->findOrCreate($fixture['variant']->id, 'GONE', inDays(2));

    foreach ([$late, $soon, $never, $shortLived] as $batch) {
        $service->apply(batchChange($fixture, StockMovementType::PurchaseReceipt, '5', $batch->id, '10'));
    }

    $query = app(StockQueryService::class);

    expect($query->allocateFefo($fixture['variant']->id, $fixture['warehouse']->id, '12'))->toBe([
        ['batch_id' => $shortLived->id, 'quantity' => '5.0000'],
        ['batch_id' => $soon->id, 'quantity' => '5.0000'],
        ['batch_id' => $late->id, 'quantity' => '2.0000'],
    ]);

    // After "GONE" expired, it is skipped.
    $this->travelTo(CarbonImmutable::now()->addDays(3));

    expect($query->allocateFefo($fixture['variant']->id, $fixture['warehouse']->id, '7'))->toBe([
        ['batch_id' => $soon->id, 'quantity' => '5.0000'],
        ['batch_id' => $late->id, 'quantity' => '2.0000'],
    ]);

    // 15 sellable units exist (soon 5, late 5, never 5): 16 is too many.
    expect(fn () => $query->allocateFefo($fixture['variant']->id, $fixture['warehouse']->id, '16'))
        ->toThrow(BusinessRuleException::class);
    expect($query->allocateFefo($fixture['variant']->id, $fixture['warehouse']->id, '15'))->toHaveCount(3);
});
