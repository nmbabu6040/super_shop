<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\RoleName;
use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use App\Policies\ModulePolicy;
use App\Services\Accounting\FiscalCalendarService;
use App\Services\Rbac\RbacSyncService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;
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

/**
 * Accounting test data: the fiscal year 2026 (twelve open periods) and four accounts.
 *
 * @return array{cash: Account, sales: Account, rent: Account, group: Account}
 */
function createAccountingFixture(): array
{
    app(FiscalCalendarService::class)->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    return [
        'cash' => Account::factory()->create(['code' => '1110', 'type' => AccountType::Asset]),
        'sales' => Account::factory()->create(['code' => '4100', 'type' => AccountType::Revenue]),
        'rent' => Account::factory()->create(['code' => '6100', 'type' => AccountType::Expense]),
        'group' => Account::factory()->create(['code' => '1000', 'type' => AccountType::Asset, 'is_postable' => false]),
    ];
}

/**
 * A balanced draft: debit cash, credit sales.
 *
 * @param  array{cash: Account, sales: Account, rent: Account, group: Account}  $fixture
 */
function balancedDraft(
    array $fixture,
    string $amount = '100.00',
    string $date = '2026-03-15',
    ?int $branchId = null,
    ?string $sourceType = null,
    ?int $sourceId = null,
    ?string $sourceEvent = null,
): JournalDraft {
    return new JournalDraft(
        CarbonImmutable::parse($date),
        'Test sale',
        [
            JournalLineDraft::debit($fixture['cash']->id, $amount),
            JournalLineDraft::credit($fixture['sales']->id, $amount),
        ],
        $branchId,
        $sourceType,
        $sourceId,
        $sourceEvent,
    );
}
