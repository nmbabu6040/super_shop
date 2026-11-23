<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only.
 *
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'code' => 'u' . fake()->unique()->numerify('######'),
            'name' => ['en' => fake()->unique()->word()],
            'symbol' => fake()->lexify('??'),
            'is_decimal' => false,
            'decimal_places' => 0,
            'base_unit_id' => null,
            'conversion_factor' => '1.000000',
            'is_active' => true,
        ];
    }
}
