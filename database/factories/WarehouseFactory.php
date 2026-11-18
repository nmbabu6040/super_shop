<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only. Never run this factory in production.
 *
 * Note: a branch already gets its default shop-floor warehouse automatically
 * (BranchObserver), so this factory builds ADDITIONAL storage/central warehouses.
 *
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->words(2, true) . ' Storage',
            'code' => strtoupper(fake()->unique()->bothify('WH-####')),
            'type' => WarehouseType::Storage,
            'is_sellable' => false,
            'is_active' => true,
            'default_for_branch_id' => null,
        ];
    }

    public function central(): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => null,
            'type' => WarehouseType::Central,
        ]);
    }
}
