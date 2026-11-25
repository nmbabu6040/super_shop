<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Product;
use App\Models\VariantBranchPrice;
use App\Services\Catalog\PriceResolver;

beforeEach(function (): void {
    seedRbac();
});

/**
 * An update payload that keeps the product as it is and adds branch prices.
 *
 * @param  array<int, array<int, string>>  $branchPrices  [variantId => [branchId => price]]
 * @return array<string, mixed>
 */
function branchPricePayload(Product $product, array $branchPrices): array
{
    return [
        'name' => ['en' => (string) $product->name],
        'category_id' => $product->category_id,
        'product_type' => 'general',
        'cost_price' => '80.00',
        'selling_price' => '100.00',
        'mrp' => '110.00',
        'is_active' => '1',
        'branch_prices' => $branchPrices,
    ];
}

it('sets, changes and clears branch prices', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), branchPricePayload($product, [
            $variant->id => [$branchA->id => '95.00', $branchB->id => '98.50'],
        ]))
        ->assertRedirect(route('admin.products.index'));

    expect(VariantBranchPrice::withoutBranchScope()->where('variant_id', $variant->id)->count())->toBe(2);
    expect(VariantBranchPrice::withoutBranchScope()->where('branch_id', $branchB->id)->value('selling_price'))->toBe('98.50');

    // Change one, clear the other.
    $this->actingAs($admin)->put(route('admin.products.update', $product), branchPricePayload($product, [
        $variant->id => [$branchA->id => '90', $branchB->id => ''],
    ]));

    expect(VariantBranchPrice::withoutBranchScope()->where('variant_id', $variant->id)->count())->toBe(1);
    expect(VariantBranchPrice::withoutBranchScope()->where('branch_id', $branchA->id)->value('selling_price'))->toBe('90.00');
});

it('leaves branch prices alone when the form does not send them', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $branch = Branch::factory()->create();
    VariantBranchPrice::query()->create(['variant_id' => $variant->id, 'branch_id' => $branch->id, 'selling_price' => '95.00']);

    $payload = branchPricePayload($product, []);
    unset($payload['branch_prices']);

    $this->actingAs(createHeadOfficeAdmin())->put(route('admin.products.update', $product), $payload);

    expect(VariantBranchPrice::withoutBranchScope()->count())->toBe(1);
});

it('refuses a branch price above the MRP', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $branch = Branch::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.products.update', $product), branchPricePayload($product, [$variant->id => [$branch->id => '120.00']]))
        ->assertSessionHas('error');

    expect(VariantBranchPrice::withoutBranchScope()->count())->toBe(0);
});

it('rejects invalid branch price input', function (array $branchPrices, string $invalidField): void {
    $product = Product::factory()->create();
    $branch = Branch::factory()->create();
    $variantId = $product->defaultVariant->id;

    $payload = branchPricePayload($product, [
        $variantId => [$branch->id => $branchPrices['value'] ?? '95'],
    ]);

    if (isset($branchPrices['foreign_variant'])) {
        $payload['branch_prices'] = [99999 => [$branch->id => '95']];
    }

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.products.update', $product), $payload)
        ->assertSessionHasErrors($invalidField);
})->with([
    'negative price' => [['value' => '-5'], 'branch_prices.*'],
    'three decimals' => [['value' => '95.555'], 'branch_prices.*'],
    'variant of another product' => [['foreign_variant' => true], 'branch_prices.99999'],
]);

it('lets branch-bound staff set prices only for their own branches and keeps other branches untouched', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    VariantBranchPrice::query()->create(['variant_id' => $variant->id, 'branch_id' => $otherBranch->id, 'selling_price' => '97.00']);

    $manager = createStaffWithRole(RoleName::InventoryManager);
    $manager->branches()->attach($ownBranch);

    // A branch the manager cannot access is refused.
    $this->actingAs($manager)
        ->put(route('admin.products.update', $product), branchPricePayload($product, [
            $variant->id => [$ownBranch->id => '95', $otherBranch->id => '90'],
        ]))
        ->assertSessionHasErrors("branch_prices.{$variant->id}.{$otherBranch->id}");

    // Only the own branch: saved, and the other branch's price is not touched.
    $this->actingAs($manager)
        ->put(route('admin.products.update', $product), branchPricePayload($product, [
            $variant->id => [$ownBranch->id => '95'],
        ]))
        ->assertRedirect(route('admin.products.index'));

    expect(VariantBranchPrice::withoutBranchScope()->where('branch_id', $ownBranch->id)->value('selling_price'))->toBe('95.00');
    expect(VariantBranchPrice::withoutBranchScope()->where('branch_id', $otherBranch->id)->value('selling_price'))->toBe('97.00');
});

it('works for head office users who focus on a single branch', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/active-branch', ['branch_id' => $branchA->id]);

    $payload = branchPricePayload($product, [$variant->id => [$branchA->id => '95', $branchB->id => '96']]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)
        ->assertRedirect(route('admin.products.index'));

    // Saving again must update, not collide with the unique (variant, branch) rule.
    $payload = branchPricePayload($product, [$variant->id => [$branchA->id => '94', $branchB->id => '93']]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)
        ->assertRedirect(route('admin.products.index'));

    expect(VariantBranchPrice::withoutBranchScope()->count())->toBe(2);
    expect(VariantBranchPrice::withoutBranchScope()->where('branch_id', $branchB->id)->value('selling_price'))->toBe('93.00');
});

it('shows branch-bound staff only the prices of their own branches on the edit page', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    VariantBranchPrice::query()->create(['variant_id' => $variant->id, 'branch_id' => $ownBranch->id, 'selling_price' => '95.00']);
    VariantBranchPrice::query()->create(['variant_id' => $variant->id, 'branch_id' => $otherBranch->id, 'selling_price' => '97.00']);

    $manager = createStaffWithRole(RoleName::InventoryManager);
    $manager->branches()->attach($ownBranch);

    $this->actingAs($manager)
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertViewHas('branchPrices', fn (array $prices): bool => array_keys($prices[$variant->id] ?? []) === [$ownBranch->id]);
});

it('resolves the branch price first and the normal price otherwise', function (): void {
    $product = Product::factory()->create();
    $variant = $product->defaultVariant;
    $branchWithPrice = Branch::factory()->create();
    $branchWithoutPrice = Branch::factory()->create();

    VariantBranchPrice::query()->create(['variant_id' => $variant->id, 'branch_id' => $branchWithPrice->id, 'selling_price' => '95.00']);

    $resolver = app(PriceResolver::class);

    expect($resolver->sellingPrice($variant, $branchWithPrice->id))->toBe('95.00');
    expect($resolver->sellingPrice($variant, $branchWithoutPrice->id))->toBe('100.00');
    expect($resolver->sellingPrice($variant))->toBe('100.00');
});
