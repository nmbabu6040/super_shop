<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductMedicineDetail;
use App\Models\ProductVariant;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Support\Barcodes\BarcodeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(Category $category, Unit $unit, array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Miniket Rice 5kg', 'bn' => 'মিনিকেট চাল ৫ কেজি'],
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'product_type' => 'general',
        'cost_price' => '300.00',
        'selling_price' => '350.00',
        'mrp' => '360.00',
        'barcode' => '4006381333931',
        'is_active' => '1',
        'is_sold_online' => '1',
    ], $overrides);
}

it('creates a product with its default variant, generated SKU and primary barcode', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', productPayload($category, $unit))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::query()->where('slug', 'miniket-rice-5kg')->firstOrFail();
    $variant = $product->defaultVariant;

    expect($product->name->toArray())->toBe(['en' => 'Miniket Rice 5kg', 'bn' => 'মিনিকেট চাল ৫ কেজি']);
    expect($variant->sku)->toBe('P' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT));
    expect($variant->selling_price)->toBe('350.00');
    expect($variant->mrp)->toBe('360.00');
    expect($variant->default_for_product_id)->toEqual($product->id);
    expect($variant->primaryBarcode->barcode)->toBe('4006381333931');
    expect($variant->primaryBarcode->is_primary)->toBeTrue();
    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(1);
});

it('generates a valid internal barcode on request', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', productPayload($category, $unit, ['barcode' => '', 'generate_barcode' => '1']));

    $barcode = ProductBarcode::query()->firstOrFail();

    expect($barcode->barcode)->toStartWith('200');
    expect(BarcodeValidator::detect($barcode->barcode))->not->toBeNull();
});

it('keeps generated slugs unique', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, ['barcode' => '']));
    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, ['barcode' => '']));

    expect(Product::query()->pluck('slug')->sort()->values()->all())->toBe(['miniket-rice-5kg', 'miniket-rice-5kg-2']);
});

it('rejects invalid product data', function (array $overrides, string $invalidField): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $existingProduct = Product::factory()->create();
    ProductBarcode::query()->create([
        'variant_id' => $existingProduct->defaultVariant->id,
        'barcode' => '5901234123457',
        'type' => 'ean13',
        'is_primary' => true,
    ]);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', productPayload($category, $unit, $overrides))
        ->assertSessionHasErrors($invalidField);

    expect(Product::query()->where('slug', 'miniket-rice-5kg')->exists())->toBeFalse();
})->with([
    'missing English name' => [['name' => ['en' => '']], 'name.en'],
    'missing category' => [['category_id' => ''], 'category_id'],
    'unknown category' => [['category_id' => 99999], 'category_id'],
    'missing unit' => [['unit_id' => ''], 'unit_id'],
    'invalid type' => [['product_type' => 'toy'], 'product_type'],
    'invalid slug' => [['slug' => 'Bad Slug!'], 'slug'],
    'invalid SKU' => [['sku' => 'bad sku!'], 'sku'],
    'duplicate SKU' => [['sku' => 'SKU-TAKEN'], 'sku'],
    'EAN with wrong check digit' => [['barcode' => '4006381333932'], 'barcode'],
    'barcode of another product' => [['barcode' => '5901234123457'], 'barcode'],
    'negative cost price' => [['cost_price' => '-1'], 'cost_price'],
    'three decimals in price' => [['selling_price' => '10.555'], 'selling_price'],
    'selling price above MRP' => [['selling_price' => '400.00', 'mrp' => '360.00'], 'selling_price'],
    'maximum stock below minimum' => [['min_stock' => '10', 'max_stock' => '5'], 'max_stock'],
]);

it('needs generic name for medicines, forces tracking and stores the details', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->post('/admin/products', productPayload($category, $unit, ['product_type' => 'medicine', 'barcode' => '']))
        ->assertSessionHasErrors('medicine.generic_name');

    $this->actingAs($admin)
        ->post('/admin/products', productPayload($category, $unit, [
            'product_type' => 'medicine',
            'barcode' => '',
            'track_batches' => '0',
            'track_expiry' => '0',
            'medicine' => [
                'generic_name' => 'Paracetamol',
                'manufacturer' => 'Square',
                'registration_number' => 'DGDA-123',
                'requires_prescription' => '0',
                'is_controlled' => '0',
            ],
        ]))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::query()->firstOrFail();

    expect($product->track_batches)->toBeTrue();
    expect($product->track_expiry)->toBeTrue();
    expect($product->medicineDetail->generic_name)->toBe('Paracetamol');
    expect($product->medicineDetail->requires_prescription)->toBeFalse();
});

it('defaults a medicine to requiring a prescription', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', productPayload($category, $unit, [
            'product_type' => 'medicine',
            'barcode' => '',
            'medicine' => ['generic_name' => 'Amoxicillin'],
        ]));

    expect(Product::query()->firstOrFail()->medicineDetail->requires_prescription)->toBeTrue();
});

it('removes the medicine details when a product stops being a medicine', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, [
        'product_type' => 'medicine',
        'barcode' => '',
        'medicine' => ['generic_name' => 'Paracetamol'],
    ]));

    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $unit, [
        'product_type' => 'general',
        'barcode' => '',
    ]));

    expect(ProductMedicineDetail::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('updates prices, SKU and barcode of the default variant', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit));

    $product = Product::query()->firstOrFail();
    $originalSku = $product->defaultVariant->sku;

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), productPayload($category, $unit, [
            'selling_price' => '355.50',
            'barcode' => '5901234123457',
        ]))
        ->assertRedirect(route('admin.products.index'));

    $variant = $product->fresh()->defaultVariant;

    expect($variant->sku)->toBe($originalSku);
    expect($variant->selling_price)->toBe('355.50');
    expect($variant->barcodes()->pluck('barcode')->all())->toBe(['5901234123457']);
    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(1);

    // A blank barcode removes it.
    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $unit, ['barcode' => '']));

    expect($variant->barcodes()->count())->toBe(0);
});

it('never changes the unit of an existing product', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, ['barcode' => '']));
    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $otherUnit, ['barcode' => '']));

    expect($product->fresh()->unit_id)->toEqual($unit->id);
});

it('stores images, switches the main image and removes images', function (): void {
    Storage::fake('public');
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, [
        'barcode' => '',
        'images' => [
            UploadedFile::fake()->image('one.png', 200, 200),
            UploadedFile::fake()->image('two.png', 200, 200),
        ],
    ]));

    $product = Product::query()->firstOrFail();
    $images = $product->images;

    expect($images)->toHaveCount(2);
    expect($product->primaryImage->id)->toEqual($images[0]->id);
    Storage::disk('public')->assertExists($images[0]->path);

    // Choose the second image as the main one.
    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $unit, [
        'barcode' => '',
        'primary_image_id' => $images[1]->id,
    ]));

    expect($product->fresh()->primaryImage->id)->toEqual($images[1]->id);

    // Remove the first image; its file goes too.
    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $unit, [
        'barcode' => '',
        'remove_images' => [$images[0]->id],
    ]));

    expect($product->fresh()->images)->toHaveCount(1);
    Storage::disk('public')->assertMissing($images[0]->path);
    Storage::disk('public')->assertExists($images[1]->path);
});

it('rejects too many, oversized or unsafe images', function (array $images): void {
    Storage::fake('public');
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', productPayload($category, $unit, ['barcode' => '', 'images' => $images]))
        ->assertSessionHasErrors();
})->with([
    'nine images' => [fn () => array_map(fn (int $index) => UploadedFile::fake()->image("image{$index}.png"), range(1, 9))],
    'svg' => [fn () => [UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml')]],
    'too large' => [fn () => [UploadedFile::fake()->image('big.png')->size(3000)]],
]);

it('rejects a main image that is not one of the product\'s images', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, ['barcode' => '']));
    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), productPayload($category, $unit, ['barcode' => '', 'primary_image_id' => 99999]))
        ->assertSessionHasErrors('primary_image_id');
});

it('deletes a product with its variant and restores both', function (): void {
    $product = Product::factory()->create();
    $variantId = $product->defaultVariant->id;
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::onlyTrashed()->whereKey($product->id)->exists())->toBeTrue();
    expect(ProductVariant::onlyTrashed()->whereKey($variantId)->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.products.restore', $product))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
    expect(ProductVariant::query()->whereKey($variantId)->exists())->toBeTrue();
});

it('refuses to restore a product whose category was deleted', function (): void {
    $product = Product::factory()->create();
    $product->delete();
    $product->category->delete();

    $this->actingAs(createHeadOfficeAdmin())
        ->post(route('admin.products.restore', $product))
        ->assertSessionHas('error');

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

it('searches by name in both languages, SKU and barcode', function (): void {
    $rice = Product::factory()->create(['name' => ['en' => 'Rice', 'bn' => 'চাল']]);
    $rice->defaultVariant->update(['sku' => 'RICE-001']);
    ProductBarcode::query()->create(['variant_id' => $rice->defaultVariant->id, 'barcode' => '4006381333931', 'type' => 'ean13', 'is_primary' => true]);
    Product::factory()->create(['name' => ['en' => 'Sugar']]);

    $admin = createHeadOfficeAdmin();

    foreach (['Rice', 'চাল', 'RICE-001', '4006381333931'] as $searchTerm) {
        $this->actingAs($admin)
            ->get('/admin/products?search=' . urlencode($searchTerm))
            ->assertViewHas('products', fn ($products): bool => $products->pluck('id')->all() === [$rice->id]);
    }
});

it('filters by category including its subcategories, and by brand and type', function (): void {
    $parentCategory = Category::factory()->create();
    $childCategory = Category::factory()->create(['parent_id' => $parentCategory->id]);
    $brand = Brand::factory()->create();

    $inChild = Product::factory()->create(['category_id' => $childCategory->id, 'brand_id' => $brand->id]);
    $inParent = Product::factory()->create(['category_id' => $parentCategory->id]);
    Product::factory()->create();

    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->get('/admin/products?category_id=' . $parentCategory->id)
        ->assertViewHas('products', fn ($products): bool => $products->pluck('id')->sort()->values()->all() === collect([$inChild->id, $inParent->id])->sort()->values()->all());

    $this->actingAs($admin)
        ->get('/admin/products?brand_id=' . $brand->id)
        ->assertViewHas('products', fn ($products): bool => $products->pluck('id')->all() === [$inChild->id]);
});

it('blocks deleting a category, brand, unit or tax rate that products use', function (): void {
    $taxRate = TaxRate::factory()->create();
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id, 'tax_rate_id' => $taxRate->id]);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.categories.destroy', $product->category))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.units.destroy', $product->unit))->assertSessionHas('error');

    expect(Category::query()->whereKey($product->category_id)->exists())->toBeTrue();
    expect(Brand::query()->whereKey($brand->id)->exists())->toBeTrue();
    expect(Unit::query()->whereKey($product->unit_id)->exists())->toBeTrue();

    // A deleted product still points to its category, so the category stays protected.
    $product->delete();

    $this->actingAs($admin)->delete(route('admin.categories.destroy', $product->category))->assertSessionHas('error');
});

it('writes price changes to the audit log', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', productPayload($category, $unit, ['barcode' => '']));
    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)->put(route('admin.products.update', $product), productPayload($category, $unit, [
        'barcode' => '',
        'selling_price' => '355.00',
    ]));

    expect(\Illuminate\Support\Facades\DB::table((new \Spatie\Activitylog\Models\Activity())->getTable())
        ->where('log_name', 'product_variant')->exists())->toBeTrue();
});

it('follows the permissions of each role', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/products')->assertForbidden();

    // Inventory managers may view, create and edit products, but not delete them.
    $inventoryManager = createStaffWithRole(RoleName::InventoryManager);

    $this->actingAs($inventoryManager)->get('/admin/products')->assertOk();
    $this->actingAs($inventoryManager)->get('/admin/products/create')->assertOk();
    $this->actingAs($inventoryManager)->get(route('admin.products.edit', $product))->assertOk();
    $this->actingAs($inventoryManager)->delete(route('admin.products.destroy', $product))->assertForbidden();
});
