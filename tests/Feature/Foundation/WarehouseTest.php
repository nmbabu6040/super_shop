<?php

declare(strict_types=1);

use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\Warehouse;

it('treats a warehouse without a branch as a central warehouse', function (): void {
    $centralWarehouse = Warehouse::factory()->central()->create();

    expect($centralWarehouse->branch_id)->toBeNull();
    expect($centralWarehouse->isCentral())->toBeTrue();
    expect($centralWarehouse->type)->toBe(WarehouseType::Central);
});

it('only lists active sellable warehouses in the sellable scope', function (): void {
    $branch = Branch::factory()->create();

    Warehouse::factory()->create([
        'branch_id' => $branch->id,
        'code' => 'STORE-ONLY',
        'is_sellable' => false,
    ]);

    $sellableCodes = Warehouse::query()->sellable()->pluck('code')->all();

    expect($sellableCodes)->toContain($branch->code . '-SF');
    expect($sellableCodes)->not->toContain('STORE-ONLY');
});
