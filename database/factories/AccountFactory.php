<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development and test data only.
 *
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => (string) fake()->unique()->numberBetween(100000, 999999),
            'name' => ['en' => fake()->unique()->words(2, true)],
            'type' => AccountType::Asset,
            'is_postable' => true,
            'is_active' => true,
        ];
    }
}
