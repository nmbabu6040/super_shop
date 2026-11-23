<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function brandPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Pran', 'bn' => 'প্রাণ'],
        'website_url' => 'https://pran.example.com',
        'sort_order' => 1,
        'is_active' => '1',
    ], $overrides);
}

it('creates a brand with a generated unique slug', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/brands', brandPayload())->assertRedirect(route('admin.brands.index'));
    $this->actingAs($admin)->post('/admin/brands', brandPayload());

    expect(Brand::query()->pluck('slug')->sort()->values()->all())->toBe(['pran', 'pran-2']);
});

it('rejects invalid brand data', function (array $overrides, string $invalidField): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/brands', brandPayload($overrides))
        ->assertSessionHasErrors($invalidField);
})->with([
    'missing English name' => [['name' => ['en' => '']], 'name.en'],
    'javascript link' => [['website_url' => 'javascript:alert(1)'], 'website_url'],
    'ftp link' => [['website_url' => 'ftp://example.com'], 'website_url'],
    'invalid slug' => [['slug' => 'Not A Slug'], 'slug'],
]);

it('updates, deletes and restores a brand', function (): void {
    $brand = Brand::factory()->create(['name' => ['en' => 'Old']]);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put(route('admin.brands.update', $brand), brandPayload(['name' => ['en' => 'Renamed']]))
        ->assertRedirect(route('admin.brands.index'));
    expect((string) $brand->fresh()->name)->toBe('Renamed');

    $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand))->assertRedirect(route('admin.brands.index'));
    expect(Brand::onlyTrashed()->whereKey($brand->id)->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.brands.restore', $brand))->assertRedirect(route('admin.brands.index'));
    expect(Brand::query()->whereKey($brand->id)->exists())->toBeTrue();
});

it('stores and removes a logo', function (): void {
    Storage::fake('public');
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/brands', brandPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ]));

    $brand = Brand::query()->firstOrFail();
    $logoPath = $brand->logo_path;

    Storage::disk('public')->assertExists($logoPath);

    $this->actingAs($admin)->put(route('admin.brands.update', $brand), brandPayload(['remove_logo' => '1']));

    expect($brand->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

it('searches in both languages and follows role permissions', function (): void {
    Brand::factory()->create(['name' => ['en' => 'Pran', 'bn' => 'প্রাণ']]);
    Brand::factory()->create(['name' => ['en' => 'Fresh']]);

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/brands?search=প্রাণ')
        ->assertViewHas('brands', fn ($brands): bool => $brands->count() === 1);

    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/brands')->assertForbidden();
});
