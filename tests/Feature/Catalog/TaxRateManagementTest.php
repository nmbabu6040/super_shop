<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\TaxRate;
use Database\Seeders\TaxRateSeeder;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taxRatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'VAT 15%', 'bn' => 'ভ্যাট ১৫%'],
        'rate' => '15',
        'is_default' => '0',
        'is_active' => '1',
    ], $overrides);
}

it('creates a tax rate and keeps the rate as an exact decimal', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/tax-rates', taxRatePayload(['rate' => '7.5']))
        ->assertRedirect(route('admin.tax-rates.index'));

    expect(TaxRate::query()->firstOrFail()->rate)->toBe('7.50');
});

it('allows only one default rate', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/tax-rates', taxRatePayload(['is_default' => '1']));
    $this->actingAs($admin)->post('/admin/tax-rates', taxRatePayload(['name' => ['en' => 'VAT 10%'], 'rate' => '10', 'is_default' => '1']));

    $defaultRates = TaxRate::query()->whereNotNull('default_marker')->get();

    expect($defaultRates)->toHaveCount(1);
    expect((string) $defaultRates->first()->name)->toBe('VAT 10%');
});

it('rejects invalid tax rate data', function (array $overrides, string $invalidField): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/tax-rates', taxRatePayload($overrides))
        ->assertSessionHasErrors($invalidField);
})->with([
    'missing English name' => [['name' => ['en' => '']], 'name.en'],
    'negative rate' => [['rate' => '-1'], 'rate'],
    'rate above 100' => [['rate' => '101'], 'rate'],
    'too many decimals' => [['rate' => '7.555'], 'rate'],
    'not a number' => [['rate' => 'abc'], 'rate'],
]);

it('refuses an inactive default rate', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/tax-rates', taxRatePayload(['is_default' => '1', 'is_active' => '0']))
        ->assertSessionHas('error');

    expect(TaxRate::query()->count())->toBe(0);
});

it('updates a tax rate and can move the default', function (): void {
    $first = TaxRate::factory()->create(['name' => ['en' => 'First']]);
    $first->forceFill(['default_marker' => 1])->save();
    $second = TaxRate::factory()->create(['name' => ['en' => 'Second']]);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put(route('admin.tax-rates.update', $second), taxRatePayload(['name' => ['en' => 'Second'], 'is_default' => '1']))
        ->assertRedirect(route('admin.tax-rates.index'));

    expect($first->fresh()->isDefault())->toBeFalse();
    expect($second->fresh()->isDefault())->toBeTrue();
});

it('refuses to delete the default rate or a rate that products use', function (): void {
    $defaultRate = TaxRate::factory()->create();
    $defaultRate->forceFill(['default_marker' => 1])->save();
    $usedRate = TaxRate::factory()->create();
    Product::factory()->create(['tax_rate_id' => $usedRate->id]);
    $unusedRate = TaxRate::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.tax-rates.destroy', $defaultRate))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.tax-rates.destroy', $usedRate))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.tax-rates.destroy', $unusedRate))->assertRedirect(route('admin.tax-rates.index'));

    expect(TaxRate::query()->count())->toBe(2);
});

it('follows the settings permissions', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/tax-rates')->assertForbidden();

    $viewer = createStaffWithRole(RoleName::Cashier);
    $viewer->givePermissionTo('settings.view');

    $this->actingAs($viewer)->get('/admin/tax-rates')->assertOk();
    $this->actingAs($viewer)->post('/admin/tax-rates', taxRatePayload())->assertForbidden();
});

it('seeds the starter rates once with a single default', function (): void {
    $this->seed(TaxRateSeeder::class);
    $this->seed(TaxRateSeeder::class);

    expect(TaxRate::query()->count())->toBe(5);
    expect(TaxRate::query()->whereNotNull('default_marker')->count())->toBe(1);
    expect(TaxRate::query()->whereNotNull('default_marker')->firstOrFail()->rate)->toBe('15.00');
});
