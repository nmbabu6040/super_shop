<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantProductPayload(Category $category, Unit $unit, array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Miniket Rice'],
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'product_type' => 'general',
        'cost_price' => '300.00',
        'selling_price' => '350.00',
        'mrp' => '360.00',
        'barcode' => '4006381333931',
        'is_active' => '1',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function twoExtraVariants(): array
{
    return [
        'variant_options' => [['name' => 'Size', 'value' => '1 kg']],
        'extra_barcodes' => "5901234123457\nABC-100",
        'extra_variants' => [
            [
                'options' => [['name' => 'size', 'value' => '2 kg']],
                'cost_price' => '600',
                'selling_price' => '690',
                'mrp' => '700',
                'barcodes' => '9780201379624',
                'is_active' => '1',
            ],
            [
                'options' => [['name' => 'size', 'value' => '5 kg']],
                'cost_price' => '1500',
                'selling_price' => '1700',
                'mrp' => '1750',
                'barcodes' => '4012345678901',
                'is_active' => '1',
            ],
        ],
    ];
}

/**
 * The variants of a product in display order, with their main barcode loaded.
 *
 * @return Collection<int, ProductVariant>
 */
function variantsOf(Product $product): Collection
{
    return ProductVariant::query()
        ->where('product_id', $product->id)
        ->with('primaryBarcode')
        ->orderBy('sort_order')
        ->get();
}

/**
 * Applies a named invalid change to the valid payload (datasets hold names, not closures).
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function mutateVariantPayload(string $scenario, array $payload): array
{
    switch ($scenario) {
        case 'invalid EAN in more barcodes':
            $payload['extra_barcodes'] = '4006381333932';
            break;
        case 'invalid EAN in a variant':
            $payload['extra_variants'][0]['barcodes'] = '9780201379625';
            break;
        case 'barcode used twice in the form':
            $payload['extra_variants'][0]['barcodes'] = '5901234123457';
            break;
        case 'barcode of another product':
            $payload['extra_variants'][1]['barcodes'] = 'FOREIGN-1';
            break;
        case 'SKU used twice in the form':
            $payload['extra_variants'][0]['sku'] = 'DUP-1';
            $payload['extra_variants'][1]['sku'] = 'DUP-1';
            break;
        case 'SKU of another product':
            $payload['extra_variants'][0]['sku'] = 'SKU-TAKEN';
            break;
        case 'extra selling price above MRP':
            $payload['extra_variants'][0]['selling_price'] = '800';
            break;
        case 'extra variant without a price':
            $payload['extra_variants'][0]['selling_price'] = '';
            break;
    }

    return $payload;
}

it('creates a product with an attributed default variant, extra variants and several barcodes', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::query()->firstOrFail();
    $baseSku = 'P' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
    $variants = variantsOf($product);

    expect($variants)->toHaveCount(3);
    expect($variants->pluck('sku')->all())->toBe([$baseSku, $baseSku . '-2', $baseSku . '-3']);
    expect($variants[0]->options)->toBe(['size' => '1 kg']);
    expect($variants[1]->options)->toBe(['size' => '2 kg']);
    expect($variants[1]->selling_price)->toBe('690.00');
    expect($variants[0]->default_for_product_id)->toEqual($product->id);
    expect($variants[1]->default_for_product_id)->toBeNull();

    // The first code is the primary one.
    expect($variants[0]->barcodes()->orderByDesc('is_primary')->orderBy('id')->pluck('barcode')->all())
        ->toBe(['4006381333931', '5901234123457', 'ABC-100']);
    expect($variants[0]->primaryBarcode->barcode)->toBe('4006381333931');
    expect($variants[1]->primaryBarcode->barcode)->toBe('9780201379624');
});

it('requires attributes for every variant once there are several', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    // The default variant has no attributes.
    $payload = twoExtraVariants();
    unset($payload['variant_options']);

    $this->actingAs($admin)
        ->post('/admin/products', variantProductPayload($category, $unit, $payload))
        ->assertSessionHasErrors('variant_options');

    // An extra variant has no attributes.
    $payload = twoExtraVariants();
    $payload['extra_variants'][1]['options'] = [];

    $this->actingAs($admin)
        ->post('/admin/products', variantProductPayload($category, $unit, $payload))
        ->assertSessionHasErrors('extra_variants.1.options');

    expect(Product::query()->count())->toBe(0);
});

it('refuses two variants with the same attributes', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $payload = twoExtraVariants();
    $payload['extra_variants'][0]['options'] = [['name' => 'SIZE', 'value' => '1 KG']];

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, $payload))
        ->assertSessionHasErrors('extra_variants.0.options');
});

it('rejects invalid, duplicated or foreign barcodes and SKUs', function (string $scenario, string $invalidField): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $otherProduct = Product::factory()->create();
    $otherProduct->defaultVariant->update(['sku' => 'SKU-TAKEN']);
    ProductBarcode::query()->create(['variant_id' => $otherProduct->defaultVariant->id, 'barcode' => 'FOREIGN-1', 'type' => 'code128', 'is_primary' => true]);

    $payload = mutateVariantPayload($scenario, twoExtraVariants());

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, $payload))
        ->assertSessionHasErrors($invalidField);

    expect(Product::query()->where('slug', 'miniket-rice')->exists())->toBeFalse();
})->with([
    ['invalid EAN in more barcodes', 'extra_barcodes'],
    ['invalid EAN in a variant', 'extra_variants.0.barcodes'],
    ['barcode used twice in the form', 'extra_variants.0.barcodes'],
    ['barcode of another product', 'extra_variants.1.barcodes'],
    ['SKU used twice in the form', 'extra_variants.1.sku'],
    ['SKU of another product', 'extra_variants.0.sku'],
    ['extra selling price above MRP', 'extra_variants.0.selling_price'],
    ['extra variant without a price', 'extra_variants.0.selling_price'],
]);

it('generates an internal barcode for an extra variant on request', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $payload = twoExtraVariants();
    $payload['extra_variants'][0]['barcodes'] = '';
    $payload['extra_variants'][0]['generate_barcode'] = '1';

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, $payload));

    $extraVariant = variantsOf(Product::query()->firstOrFail())[1];

    expect($extraVariant->primaryBarcode->barcode)->toStartWith('200');
});

it('keeps, changes, adds and removes variants on update', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));

    $product = Product::query()->firstOrFail();
    $originalVariants = variantsOf($product);
    $defaultVariantId = $originalVariants[0]->id;
    $firstExtraId = $originalVariants[1]->id;
    $secondExtraId = $originalVariants[2]->id;

    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, [
        'variant_options' => [['name' => 'size', 'value' => '1 kg']],
        'extra_variants' => [
            // Existing variant: new price, same id.
            [
                'id' => $firstExtraId,
                'options' => [['name' => 'size', 'value' => '2 kg']],
                'cost_price' => '600',
                'selling_price' => '699',
                'mrp' => '700',
                'barcodes' => '9780201379624',
            ],
            // A brand-new variant; the second old one is dropped.
            [
                'options' => [['name' => 'size', 'value' => '10 kg']],
                'cost_price' => '3000',
                'selling_price' => '3300',
                'mrp' => '3400',
                'barcodes' => '',
            ],
        ],
    ]))->assertRedirect(route('admin.products.index'));

    $activeVariants = variantsOf($product);

    expect($activeVariants)->toHaveCount(3);
    expect($activeVariants[0]->id)->toEqual($defaultVariantId);
    expect($activeVariants[1]->id)->toEqual($firstExtraId);
    expect($activeVariants[1]->selling_price)->toBe('699.00');
    expect($activeVariants[2]->options)->toBe(['size' => '10 kg']);
    expect(ProductVariant::onlyTrashed()->whereKey($secondExtraId)->exists())->toBeTrue();

    // The removed variant's barcode stays reserved.
    expect(ProductBarcode::query()->where('barcode', '4012345678901')->exists())->toBeTrue();
});

it('removes all extra variants when the form sends none', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));
    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, [
        'barcode' => '4006381333931',
    ]));

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(1);
    expect(ProductVariant::onlyTrashed()->where('product_id', $product->id)->count())->toBe(2);
    expect($product->fresh()->defaultVariant->options)->toBeNull();
});

it('refuses an extra variant id that belongs to another product', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));
    $product = Product::query()->where('slug', 'miniket-rice')->firstOrFail();

    // A second product with its own extra variant.
    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, [
        'name' => ['en' => 'Foreign'],
        'barcode' => '',
        'variant_options' => [['name' => 'size', 'value' => '1']],
        'extra_variants' => [[
            'options' => [['name' => 'size', 'value' => '2']],
            'cost_price' => '1',
            'selling_price' => '2',
            'mrp' => '3',
            'barcodes' => '',
        ]],
    ]));

    $foreignProduct = Product::query()->where('slug', 'foreign')->firstOrFail();
    $foreignExtraId = variantsOf($foreignProduct)[1]->id;

    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, [
        'variant_options' => [['name' => 'size', 'value' => '1 kg']],
        'extra_variants' => [[
            'id' => $foreignExtraId,
            'options' => [['name' => 'size', 'value' => '9 kg']],
            'cost_price' => '1',
            'selling_price' => '2',
            'mrp' => '3',
        ]],
    ]))->assertSessionHasErrors('extra_variants.0.id');

    expect(ProductVariant::query()->whereKey($foreignExtraId)->value('product_id'))->toEqual($foreignProduct->id);
});

it('restores a product together with all its variants', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));
    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)->delete(route('admin.products.destroy', $product));
    expect(ProductVariant::onlyTrashed()->where('product_id', $product->id)->count())->toBe(3);

    $this->actingAs($admin)->post(route('admin.products.restore', $product));
    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(3);
});

it('does not bring back a variant that was removed before the product was deleted', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));
    $product = Product::query()->firstOrFail();
    $removedVariantId = variantsOf($product)[2]->id;

    // Remove the last extra variant first ...
    $payload = twoExtraVariants();
    array_pop($payload['extra_variants']);
    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, $payload));

    // ... then delete and restore the product some time later.
    $this->travel(5)->seconds();
    $this->actingAs($admin)->delete(route('admin.products.destroy', $product));
    $this->travel(5)->seconds();
    $this->actingAs($admin)->post(route('admin.products.restore', $product));

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(2);
    expect(ProductVariant::onlyTrashed()->whereKey($removedVariantId)->exists())->toBeTrue();
});
