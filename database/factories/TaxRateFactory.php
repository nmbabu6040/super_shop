<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only.
 *
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => 'VAT ' . fake()->unique()->numberBetween(1, 99)],
            'rate' => '15.00',
            'is_active' => true,
        ];
    }
}
