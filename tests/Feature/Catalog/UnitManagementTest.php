<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Unit;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function unitPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'kg',
        'name' => ['en' => 'Kilogram', 'bn' => 'কেজি'],
        'symbol' => 'kg',
        'is_decimal' => '1',
        'decimal_places' => 3,
        'is_active' => '1',
    ], $overrides);
}

it('creates a base unit and a derived unit', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/units', unitPayload())->assertRedirect(route('admin.units.index'));

    $kilogram = Unit::query()->where('code', 'kg')->firstOrFail();

    expect($kilogram->conversion_factor)->toBe('1.000000');
    expect($kilogram->base_unit_id)->toBeNull();

    $this->actingAs($admin)->post('/admin/units', unitPayload([
        'code' => 'g',
        'name' => ['en' => 'Gram'],
        'symbol' => 'g',
        'is_decimal' => '0',
        'decimal_places' => 0,
        'base_unit_id' => $kilogram->id,
        'conversion_factor' => '0.001',
    ]))->assertRedirect(route('admin.units.index'));

    $gram = Unit::query()->where('code', 'g')->firstOrFail();

    expect($gram->base_unit_id)->toEqual($kilogram->id);
    expect($gram->conversion_factor)->toBe('0.001000');
});

it('treats a unit without a base as 1:1 whatever factor was sent', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/units', unitPayload(['conversion_factor' => '5']));

    expect(Unit::query()->where('code', 'kg')->firstOrFail()->conversion_factor)->toBe('1.000000');
});

it('rejects invalid unit data', function (array $overrides, string $invalidField): void {
    Unit::factory()->create(['code' => 'taken']);
    $derivedUnit = Unit::factory()->create(['base_unit_id' => Unit::factory()->create()->id, 'conversion_factor' => '2']);

    $payload = unitPayload(array_merge(['base_unit_id' => null], $overrides));

    if (($overrides['base_unit_id'] ?? null) === 'derived') {
        $payload['base_unit_id'] = $derivedUnit->id;
    }

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/units', $payload)
        ->assertSessionHasErrors($invalidField);
})->with([
    'duplicate code' => [['code' => 'taken'], 'code'],
    'uppercase code' => [['code' => 'KG'], 'code'],
    'code with spaces' => [['code' => 'my unit'], 'code'],
    'missing English name' => [['name' => ['en' => '']], 'name.en'],
    'missing symbol' => [['symbol' => ''], 'symbol'],
    'too many decimal places' => [['decimal_places' => 9], 'decimal_places'],
    'base unit that is itself derived' => [['base_unit_id' => 'derived', 'conversion_factor' => '2'], 'base_unit_id'],
    'zero factor' => [['base_unit_id' => 1, 'conversion_factor' => '0'], 'conversion_factor'],
]);

it('refuses decimal places on a whole-number unit', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/units', unitPayload(['is_decimal' => '0', 'decimal_places' => 2]))
        ->assertSessionHas('error');

    expect(Unit::query()->where('code', 'kg')->exists())->toBeFalse();
});

it('requires a positive factor for a derived unit', function (): void {
    $baseUnit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/units', unitPayload([
            'code' => 'half',
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => null,
        ]))
        ->assertSessionHas('error');

    expect(Unit::query()->where('code', 'half')->exists())->toBeFalse();
});

it('never changes the code, and keeps unit chains one step deep', function (): void {
    $baseUnit = Unit::factory()->create(['code' => 'base_unit']);
    $otherUnit = Unit::factory()->create(['code' => 'other_unit']);
    $derivedUnit = Unit::factory()->create(['code' => 'small', 'base_unit_id' => $baseUnit->id, 'conversion_factor' => '0.5']);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put(route('admin.units.update', $derivedUnit), unitPayload([
            'code' => 'hacked',
            'name' => ['en' => 'Renamed'],
            'symbol' => 'sm',
            'is_decimal' => '0',
            'decimal_places' => 0,
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => '0.25',
        ]))
        ->assertRedirect(route('admin.units.index'));

    $freshUnit = $derivedUnit->fresh();

    expect($freshUnit->code)->toBe('small');
    expect((string) $freshUnit->name)->toBe('Renamed');
    expect($freshUnit->conversion_factor)->toBe('0.250000');

    // The base unit of "small" cannot itself become a derived unit.
    $this->actingAs($admin)
        ->put(route('admin.units.update', $baseUnit), unitPayload([
            'is_decimal' => '0',
            'decimal_places' => 0,
            'base_unit_id' => $otherUnit->id,
            'conversion_factor' => '2',
        ]))
        ->assertSessionHas('error');

    expect($baseUnit->fresh()->base_unit_id)->toBeNull();
});

it('refuses a unit being its own base', function (): void {
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.units.update', $unit), unitPayload([
            'is_decimal' => '0',
            'decimal_places' => 0,
            'base_unit_id' => $unit->id,
            'conversion_factor' => '2',
        ]))
        ->assertSessionHasErrors('base_unit_id');
});

it('deletes a unit, but not one that other units are based on', function (): void {
    $baseUnit = Unit::factory()->create();
    Unit::factory()->create(['base_unit_id' => $baseUnit->id, 'conversion_factor' => '2']);
    $lonelyUnit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.units.destroy', $baseUnit))->assertSessionHas('error');
    expect(Unit::query()->whereKey($baseUnit->id)->exists())->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.units.destroy', $lonelyUnit))->assertRedirect(route('admin.units.index'));
    expect(Unit::query()->whereKey($lonelyUnit->id)->exists())->toBeFalse();
});

it('follows the permissions of each role', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/units')->assertForbidden();

    // Inventory managers may only view units.
    $inventoryManager = createStaffWithRole(RoleName::InventoryManager);

    $this->actingAs($inventoryManager)->get('/admin/units')->assertOk();
    $this->actingAs($inventoryManager)->get('/admin/units/create')->assertForbidden();
});
