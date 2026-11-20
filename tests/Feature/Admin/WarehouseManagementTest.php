<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Warehouse;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validWarehousePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Back Store',
        'code' => 'BS-01',
        'type' => 'storage',
        'branch_id' => null,
        'is_sellable' => '0',
        'is_active' => '1',
    ], $overrides);
}

it('lets the head office create a storage warehouse for a branch', function (): void {
    $branch = Branch::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/warehouses', validWarehousePayload(['branch_id' => $branch->id]))
        ->assertRedirect(route('admin.warehouses.index'));

    $warehouse = Warehouse::query()->where('code', 'BS-01')->firstOrFail();

    expect($warehouse->branch_id)->toEqual($branch->id);
    expect($warehouse->default_for_branch_id)->toBeNull();
    expect($warehouse->is_sellable)->toBeFalse();
});

it('lets a branch manager create warehouses only inside their own branch', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $manager = createBranchStaff($ownBranch);
    $manager->givePermissionTo('warehouses.create');

    $this->actingAs($manager)
        ->post('/admin/warehouses', validWarehousePayload(['branch_id' => $otherBranch->id]))
        ->assertSessionHasErrors('branch_id');

    expect(Warehouse::query()->where('code', 'BS-01')->exists())->toBeFalse();

    $this->actingAs($manager)
        ->post('/admin/warehouses', validWarehousePayload(['branch_id' => $ownBranch->id]))
        ->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::query()->where('code', 'BS-01')->exists())->toBeTrue();
});

it('lets only head office users create a central warehouse', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/warehouses', validWarehousePayload(['type' => 'central', 'code' => 'CW-1']))
        ->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::query()->where('code', 'CW-1')->firstOrFail()->isCentral())->toBeTrue();

    $ownBranch = Branch::factory()->create();
    $manager = createBranchStaff($ownBranch);
    $manager->givePermissionTo('warehouses.create');

    $this->actingAs($manager)
        ->post('/admin/warehouses', validWarehousePayload(['type' => 'central', 'code' => 'CW-2']))
        ->assertSessionHasErrors('type');

    expect(Warehouse::query()->where('code', 'CW-2')->exists())->toBeFalse();
});

it('rejects invalid warehouse data', function (array $overrides, string $invalidField): void {
    $branch = Branch::factory()->create();
    Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'TAKEN-1']);

    $payload = validWarehousePayload(array_merge(['branch_id' => $branch->id], $overrides));

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/warehouses', $payload)
        ->assertSessionHasErrors($invalidField);

    expect(Warehouse::query()->where('name', 'Back Store')->exists())->toBeFalse();
})->with([
    'duplicate code' => [['code' => 'TAKEN-1'], 'code'],
    'invalid code' => [['code' => 'bad code!'], 'code'],
    'missing name' => [['name' => ''], 'name'],
    'shop floor type cannot be created by hand' => [['type' => 'shop_floor'], 'type'],
    'storage without branch' => [['branch_id' => null], 'branch_id'],
    'central with a branch' => [['type' => 'central'], 'branch_id'],
]);

it('keeps the code of a deleted warehouse reserved', function (): void {
    $branch = Branch::factory()->create();
    $deletedWarehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'OLD-1']);
    $deletedWarehouse->delete();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/warehouses', validWarehousePayload(['code' => 'OLD-1', 'branch_id' => $branch->id]))
        ->assertSessionHasErrors('code');
});

it('never changes code, type or branch of an existing warehouse', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'FIX-1', 'name' => 'Old']);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.warehouses.update', $warehouse), [
            'name' => 'Renamed',
            'code' => 'HACKED',
            'type' => 'central',
            'branch_id' => $otherBranch->id,
            'is_sellable' => '1',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.warehouses.index'));

    $freshWarehouse = $warehouse->fresh();

    expect($freshWarehouse->name)->toBe('Renamed');
    expect($freshWarehouse->code)->toBe('FIX-1');
    expect($freshWarehouse->branch_id)->toEqual($branch->id);
    expect($freshWarehouse->type->value)->toBe('storage');
    expect($freshWarehouse->is_sellable)->toBeTrue();
});

it('protects the default shop-floor warehouse of a branch', function (): void {
    $branch = Branch::factory()->create(['code' => 'PRO-1']);
    $defaultWarehouse = $branch->defaultWarehouse;
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.warehouses.destroy', $defaultWarehouse))
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->put(route('admin.warehouses.update', $defaultWarehouse), [
            'name' => $defaultWarehouse->name,
            'is_sellable' => '1',
            'is_active' => '0',
        ])
        ->assertSessionHas('error');

    $freshWarehouse = $defaultWarehouse->fresh();

    expect($freshWarehouse)->not->toBeNull();
    expect($freshWarehouse->is_active)->toBeTrue();
});

it('deletes and restores an ordinary warehouse', function (): void {
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'ORD-1']);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.warehouses.destroy', $warehouse))
        ->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::withoutBranchScope()->onlyTrashed()->where('code', 'ORD-1')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.warehouses.restore', $warehouse))
        ->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::query()->where('code', 'ORD-1')->exists())->toBeTrue();
});

it('refuses to restore a warehouse whose branch is deleted', function (): void {
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'ORP-1']);
    $warehouse->delete();
    $branch->delete();

    $this->actingAs(createHeadOfficeAdmin())
        ->post(route('admin.warehouses.restore', $warehouse))
        ->assertSessionHas('error');

    expect(Warehouse::query()->where('code', 'ORP-1')->exists())->toBeFalse();
});

it('shows a branch manager only the warehouses of their branch plus central ones', function (): void {
    $ownBranch = Branch::factory()->create(['name' => 'Alpha']);
    Branch::factory()->create(['name' => 'Omega']);
    Warehouse::factory()->central()->create(['name' => 'Central Depot', 'code' => 'CEN-1']);

    $this->actingAs(createBranchStaff($ownBranch))
        ->get('/admin/warehouses')
        ->assertOk()
        ->assertSee('Alpha - Shop Floor')
        ->assertSee('Central Depot')
        ->assertDontSee('Omega - Shop Floor');
});

it('keeps branch managers from changing warehouses of another branch', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $foreignWarehouse = Warehouse::factory()->create(['branch_id' => $otherBranch->id]);

    $this->actingAs(createBranchStaff($ownBranch))
        ->put(route('admin.warehouses.update', $foreignWarehouse), [
            'name' => 'Hijacked',
            'is_sellable' => '0',
            'is_active' => '1',
        ])
        ->assertNotFound();

    expect($foreignWarehouse->fresh()->name)->not->toBe('Hijacked');
});
