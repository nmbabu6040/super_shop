<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Exceptions\BranchAccessDeniedException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockCost;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\StockQueryService;
use App\Services\Inventory\StockService;
use App\Support\Inventory\StockChange;

beforeEach(function (): void {
    seedRbac();
});

function receiveInto(Warehouse $warehouse, int $variantId, string $quantity = '10'): StockMovement
{
    return app(StockService::class)->apply(new StockChange(
        $warehouse->id,
        $variantId,
        StockMovementType::PurchaseReceipt,
        $quantity,
        unitCost: '50'
    ));
}

it('lets branch-bound staff change stock of their own branch only', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ownWarehouse = Warehouse::factory()->create(['branch_id' => $ownBranch->id]);
    $otherWarehouse = Warehouse::factory()->create(['branch_id' => $otherBranch->id]);
    $variantId = Product::factory()->create()->defaultVariant->id;

    $this->actingAs(createBranchStaff($ownBranch));

    expect(receiveInto($ownWarehouse, $variantId)->branch_id)->toEqual($ownBranch->id);

    expect(fn () => receiveInto($otherWarehouse, $variantId))->toThrow(BranchAccessDeniedException::class);

    // The refused attempt left nothing behind, not even an empty row.
    expect(StockLevel::withoutBranchScope()->where('warehouse_id', $otherWarehouse->id)->count())->toBe(0);
    expect(StockCost::query()->where('warehouse_id', $otherWarehouse->id)->count())->toBe(0);
    expect(StockMovement::withoutBranchScope()->where('warehouse_id', $otherWarehouse->id)->count())->toBe(0);
});

it('lets only unrestricted users change the stock of a central warehouse', function (): void {
    $branch = Branch::factory()->create();
    $central = Warehouse::factory()->central()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    $this->actingAs(createBranchStaff($branch));

    expect(fn () => receiveInto($central, $variantId))->toThrow(BranchAccessDeniedException::class);

    $this->actingAs(createHeadOfficeAdmin());

    $movement = receiveInto($central, $variantId);

    expect($movement->branch_id)->toBeNull();
    expect(StockLevel::withoutBranchScope()->where('warehouse_id', $central->id)->firstOrFail()->branch_id)->toBeNull();
});

it('shows branch-bound staff their own stock and the central stock, but no other branch', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ownWarehouse = Warehouse::factory()->create(['branch_id' => $ownBranch->id]);
    $otherWarehouse = Warehouse::factory()->create(['branch_id' => $otherBranch->id]);
    $central = Warehouse::factory()->central()->create();
    $variantId = Product::factory()->create()->defaultVariant->id;

    $this->actingAs(createHeadOfficeAdmin());

    receiveInto($ownWarehouse, $variantId, '1');
    receiveInto($otherWarehouse, $variantId, '10');
    receiveInto($central, $variantId, '100');

    $this->actingAs(createBranchStaff($ownBranch));

    expect(StockLevel::query()->pluck('warehouse_id')->sort()->values()->all())
        ->toEqual(collect([$ownWarehouse->id, $central->id])->sort()->values()->all());
    expect(StockMovement::query()->count())->toBe(2);
    expect(app(StockQueryService::class)->onHand($variantId))->toBe('101.0000');

    $this->actingAs(createHeadOfficeAdmin());

    expect(app(StockQueryService::class)->onHand($variantId))->toBe('111.0000');
});
