<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Development and test data only.
 *
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $englishName = fake()->unique()->company();

        return [
            'name' => ['en' => $englishName],
            'slug' => Str::slug($englishName) . '-' . fake()->unique()->numerify('####'),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
