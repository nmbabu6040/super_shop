<?php

declare(strict_types=1);

use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

it('creates a default shop-floor warehouse for every new branch', function (): void {
    $branch = Branch::factory()->create(['code' => 'BR-001', 'name' => 'Gulshan']);

    $defaultWarehouse = $branch->defaultWarehouse;

    expect($defaultWarehouse)->not->toBeNull();
    expect($defaultWarehouse->code)->toBe('BR-001-SF');
    expect($defaultWarehouse->type)->toBe(WarehouseType::ShopFloor);
    expect($defaultWarehouse->is_sellable)->toBeTrue();
    expect($defaultWarehouse->branch_id)->toEqual($branch->id);
});

it('rejects a duplicate branch code', function (): void {
    Branch::factory()->create(['code' => 'DUP-1']);
    Branch::factory()->create(['code' => 'DUP-1']);
})->throws(QueryException::class);

it('allows only one default warehouse per branch at database level', function (): void {
    $branch = Branch::factory()->create();

    // The branch already owns its default warehouse, so a second one must be refused.
    Warehouse::factory()->create([
        'branch_id' => $branch->id,
        'default_for_branch_id' => $branch->id,
    ]);
})->throws(QueryException::class);

it('keeps the code of a soft-deleted branch reserved', function (): void {
    $branch = Branch::factory()->create(['code' => 'OLD-1']);
    $branch->delete();

    expect(Branch::withTrashed()->where('code', 'OLD-1')->exists())->toBeTrue();

    Branch::factory()->create(['code' => 'OLD-1']);
})->throws(QueryException::class);

it('stamps the authenticated user as creator and last updater', function (): void {
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($author);
    $branch = Branch::factory()->create();

    $this->actingAs($editor);
    $branch->update(['name' => 'Renamed Branch']);

    $freshBranch = $branch->fresh();

    expect($freshBranch->created_by)->toEqual($author->id);
    expect($freshBranch->updated_by)->toEqual($editor->id);
});

it('stores the opening balance as an exact decimal string', function (): void {
    $branch = Branch::factory()->create(['opening_balance' => '1500.50']);

    expect($branch->fresh()->opening_balance)->toBe('1500.50');
});

it('writes audit log entries when a branch is created', function (): void {
    Branch::factory()->create();

    expect(Activity::query()->count())->toBeGreaterThan(0);
});
