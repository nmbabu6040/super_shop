<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use App\Policies\ModulePolicy;
use App\Services\Rbac\RbacSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests: full HTTP/database tests. The Vite manifest is not needed in tests.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
    })
    ->in('Feature');

// Unit tests still boot the framework so config() and url() are available.
pest()->extend(TestCase::class)->in('Unit');

/**
 * A password that satisfies the application's password policy.
 */
const STRONG_TEST_PASSWORD = 'Str0ng!Passw0rd#2026';

/**
 * Create all permissions and system roles (what RbacSeeder does).
 */
function seedRbac(): void
{
    app(RbacSyncService::class)->sync();
}

/**
 * A branch manager restricted to the given branches. Call seedRbac() first.
 */
function createBranchStaff(Branch ...$branches): User
{
    $staffUser = User::factory()->create();
    $staffUser->assignRole(RoleName::BranchManager->value);
    $staffUser->branches()->attach(array_map(
        static fn (Branch $branch): int => $branch->id,
        $branches
    ));

    return $staffUser;
}

/**
 * A verified storefront customer with a known password. Call seedRbac() first.
 *
 * @param  array<string, mixed>  $attributes
 */
function createCustomer(array $attributes = []): User
{
    $customer = User::factory()->create(array_merge(['password' => STRONG_TEST_PASSWORD], $attributes));
    $customer->assignRole(RoleName::Customer->value);

    return $customer;
}

/**
 * A verified staff user with the given role and a known password. Call seedRbac() first.
 *
 * @param  array<string, mixed>  $attributes
 */
function createStaffWithRole(RoleName $roleName, array $attributes = []): User
{
    $staffUser = User::factory()->create(array_merge(['password' => STRONG_TEST_PASSWORD], $attributes));
    $staffUser->assignRole($roleName->value);

    return $staffUser;
}

/**
 * A head office administrator who may access every branch. Call seedRbac() first.
 */
function createHeadOfficeAdmin(): User
{
    return createStaffWithRole(RoleName::HeadOfficeAdmin, ['has_all_branch_access' => true]);
}

/**
 * A minimal policy for the "warehouses" module, used to test ModulePolicy behaviour.
 */
function makeWarehousePolicy(): ModulePolicy
{
    return new class () extends ModulePolicy {
        protected function module(): string
        {
            return 'warehouses';
        }
    };
}
