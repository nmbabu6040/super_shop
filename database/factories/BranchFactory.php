<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BranchStatus;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only. Never run this factory in production.
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR-####')),
            'is_head_office' => false,
            'status' => BranchStatus::Active,
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'district' => fake()->city(),
            'postal_code' => fake()->numerify('####'),
            'phone' => '01' . fake()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'opening_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'opening_balance' => fake()->numberBetween(0, 500000),
            'working_hours' => collect(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])
                ->mapWithKeys(fn (string $day): array => [$day => ['open' => '09:00', 'close' => '21:00']])
                ->all(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BranchStatus::Closed,
        ]);
    }
}
