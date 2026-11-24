<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Development and test data only. A created product always gets its default variant.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $englishName = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => null,
            'unit_id' => Unit::factory(),
            'tax_rate_id' => null,
            'product_type' => ProductType::General,
            'name' => ['en' => $englishName, 'bn' => 'পণ্য ' . fake()->unique()->numerify('####')],
            'slug' => Str::slug($englishName) . '-' . fake()->unique()->numerify('####'),
            'is_active' => true,
            'is_sold_online' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if (! $product->variants()->exists()) {
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'default_for_product_id' => $product->id,
                    'sku' => 'SKU-' . $product->id,
                ]);
            }
        });
    }
}
