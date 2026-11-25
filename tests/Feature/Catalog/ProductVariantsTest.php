<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Unit;

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

it('creates a product with an attributed default variant, extra variants and several barcodes', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::query()->firstOrFail();
    $baseSku = 'P' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
    $variants = $product->variants;

    expect($variants)->toHaveCount(3);
    expect($variants->pluck('sku')->all())->toBe([$baseSku, $baseSku . '-2', $baseSku . '-3']);
    expect($variants[0]->options)->toBe(['size' => '1 kg']);
    expect($variants[1]->options)->toBe(['size' => '2 kg']);
    expect($variants[1]->selling_price)->toBe('690.00');
    expect($variants[0]->isDefault())->toBeTrue();
    expect($variants[1]->isDefault())->toBeFalse();

    // The first code is the primary one.
    expect($variants[0]->barcodes()->orderByDesc('is_primary')->orderBy('id')->pluck('barcode')->all())
        ->toBe(['4006381333931', '5901234123457', 'ABC-100']);

    $variants->load('primaryBarcode');
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

it('rejects invalid, duplicated or foreign barcodes and SKUs', function (callable $mutate, string $invalidField): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $otherProduct = Product::factory()->create();
    $otherProduct->defaultVariant->update(['sku' => 'SKU-TAKEN']);
    ProductBarcode::query()->create(['variant_id' => $otherProduct->defaultVariant->id, 'barcode' => 'FOREIGN-1', 'type' => 'code128', 'is_primary' => true]);

    $payload = $mutate(twoExtraVariants());

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, $payload))
        ->assertSessionHasErrors($invalidField);

    expect(Product::query()->where('slug', 'miniket-rice')->exists())->toBeFalse();
})->with([
    'invalid EAN in more barcodes' => [fn(array $p): array => [...$p, 'extra_barcodes' => '4006381333932'], 'extra_barcodes'],
    'invalid EAN in a variant' => [function (array $p): array {
        $p['extra_variants'][0]['barcodes'] = '9780201379625';

        return $p;
    }, 'extra_variants.0.barcodes'],
    'barcode used twice in the form' => [function (array $p): array {
        $p['extra_variants'][0]['barcodes'] = '5901234123457';

        return $p;
    }, 'extra_variants.0.barcodes'],
    'barcode of another product' => [function (array $p): array {
        $p['extra_variants'][1]['barcodes'] = 'FOREIGN-1';

        return $p;
    }, 'extra_variants.1.barcodes'],
    'SKU used twice in the form' => [function (array $p): array {
        $p['extra_variants'][0]['sku'] = 'DUP-1';
        $p['extra_variants'][1]['sku'] = 'DUP-1';

        return $p;
    }, 'extra_variants.1.sku'],
    'SKU of another product' => [function (array $p): array {
        $p['extra_variants'][0]['sku'] = 'SKU-TAKEN';

        return $p;
    }, 'extra_variants.0.sku'],
    'extra selling price above MRP' => [function (array $p): array {
        $p['extra_variants'][0]['selling_price'] = '800';

        return $p;
    }, 'extra_variants.0.selling_price'],
    'extra variant without a price' => [function (array $p): array {
        $p['extra_variants'][0]['selling_price'] = '';

        return $p;
    }, 'extra_variants.0.selling_price'],
]);

it('generates an internal barcode for an extra variant on request', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $payload = twoExtraVariants();
    $payload['extra_variants'][0]['barcodes'] = '';
    $payload['extra_variants'][0]['generate_barcode'] = '1';

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/products', variantProductPayload($category, $unit, $payload));

    $extraVariant = Product::query()->firstOrFail()->variants[1];
    $extraVariant->load('primaryBarcode');

    expect($extraVariant->primaryBarcode->barcode)->toStartWith('200');
});

it('keeps, changes, adds and removes variants on update', function (): void {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/products', variantProductPayload($category, $unit, twoExtraVariants()));

    $product = Product::query()->firstOrFail();
    [$defaultVariant, $firstExtra, $secondExtra] = $product->variants->all();

    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, [
        'variant_options' => [['name' => 'size', 'value' => '1 kg']],
        'extra_variants' => [
            // Existing variant: new price, same id.
            [
                'id' => $firstExtra->id,
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

    $activeVariants = ProductVariant::query()->where('product_id', $product->id)->orderBy('sort_order')->get();

    expect($activeVariants)->toHaveCount(3);
    expect($activeVariants[0]->id)->toEqual($defaultVariant->id);
    expect($activeVariants[1]->id)->toEqual($firstExtra->id);
    expect($activeVariants[1]->selling_price)->toBe('699.00');
    expect($activeVariants[2]->options)->toBe(['size' => '10 kg']);
    expect(ProductVariant::onlyTrashed()->whereKey($secondExtra->id)->exists())->toBeTrue();

    // The removed variant's SKU and barcode stay reserved.
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
    $product = Product::query()->firstOrFail();

    $foreignProduct = Product::factory()->create();
    $foreignExtra = ProductVariant::factory()->create(['product_id' => $foreignProduct->id, 'sku' => 'FOREIGN-EXTRA']);

    $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, [
        'variant_options' => [['name' => 'size', 'value' => '1 kg']],
        'extra_variants' => [[
            'id' => $foreignExtra->id,
            'options' => [['name' => 'size', 'value' => '9 kg']],
            'cost_price' => '1',
            'selling_price' => '2',
            'mrp' => '3',
        ]],
    ]))->assertSessionHasErrors('extra_variants.0.id');

    expect($foreignExtra->fresh()->sku)->toBe('FOREIGN-EXTRA');
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
    $removedVariant = $product->variants[2];

    // Remove the last extra variant first ...
    $payload = twoExtraVariants();
    array_pop($payload['extra_variants']);
    // $this->actingAs($admin)->put(route('admin.products.update', $product), variantProductPayload($category, $unit, $payload));

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), variantProductPayload($category, $unit, $payload))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHasNoErrors();

    dump([
        'after_update_removed_variant_deleted_at' => ProductVariant::withTrashed()
            ->whereKey($removedVariant->id)
            ->value('deleted_at'),
        'after_update_product_deleted_at' => Product::withTrashed()
            ->whereKey($product->id)
            ->value('deleted_at'),
    ]);

    // ... then delete and restore the product some time later.
    // $this->travel(5)->seconds();
    // $this->actingAs($admin)->delete(route('admin.products.destroy', $product));
    // $this->travel(5)->seconds();
    // $this->actingAs($admin)->post(route('admin.products.restore', $product));

    $this->travel(5)->seconds();
    $this->actingAs($admin)->delete(route('admin.products.destroy', $product));

    dump([
        'product_deleted_at' => Product::withTrashed()
            ->whereKey($product->id)
            ->value('deleted_at'),

        'removed_variant_id' => $removedVariant->id,

        'removed_variant_deleted_at' => ProductVariant::withTrashed()
            ->whereKey($removedVariant->id)
            ->value('deleted_at'),

        'all_variants' => ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get(['id', 'deleted_at'])
            ->toArray(),
    ]);

    $this->travel(5)->seconds();
    $this->actingAs($admin)->post(route('admin.products.restore', $product));

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(2);
    expect(ProductVariant::onlyTrashed()->whereKey($removedVariant->id)->exists())->toBeTrue();
});
