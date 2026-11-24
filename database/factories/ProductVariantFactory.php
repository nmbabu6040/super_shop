<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only.
 *
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'cost_price' => '80.00',
            'selling_price' => '100.00',
            'mrp' => '110.00',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
