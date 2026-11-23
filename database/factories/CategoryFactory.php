<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Development and test data only.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $englishName = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ['en' => $englishName, 'bn' => 'বিভাগ ' . fake()->unique()->numerify('####')],
            'slug' => Str::slug($englishName) . '-' . fake()->unique()->numerify('####'),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
