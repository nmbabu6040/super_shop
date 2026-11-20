<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Exceptions\BranchAccessDeniedException;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Branch\BranchContext;

beforeEach(function (): void {
    seedRbac();
});

it('shows a branch-bound user only their own branch plus shared records', function (): void {
    $branchA = Branch::factory()->create(['code' => 'AAA']);
    Branch::factory()->create(['code' => 'BBB']);
    Warehouse::factory()->central()->create(['code' => 'CENTRAL-1']);

    $this->actingAs(createBranchStaff($branchA));

    $warehouseCodes = Warehouse::query()->pluck('code')->all();

    expect($warehouseCodes)->toContain('AAA-SF', 'CENTRAL-1');
    expect($warehouseCodes)->not->toContain('BBB-SF');
});

it('shows a user with all-branch access every branch', function (): void {
    Branch::factory()->create(['code' => 'AAA']);
    Branch::factory()->create(['code' => 'BBB']);

    $headOfficeUser = User::factory()->withAllBranchAccess()->create();
    $headOfficeUser->assignRole(RoleName::HeadOfficeAdmin->value);

    $this->actingAs($headOfficeUser);

    expect(Warehouse::query()->pluck('code')->all())->toContain('AAA-SF', 'BBB-SF');
});

it('does not restrict customers, whose access is limited by permissions and own-data policies', function (): void {
    Branch::factory()->create(['code' => 'AAA']);
    Branch::factory()->create(['code' => 'BBB']);

    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer);

    expect(Warehouse::query()->pluck('code')->all())->toContain('AAA-SF', 'BBB-SF');
});

it('shows nothing to a logged-in user without roles (fail-closed)', function (): void {
    Branch::factory()->create(['code' => 'AAA']);

    $this->actingAs(User::factory()->create());

    expect(Warehouse::query()->count())->toBe(0);
});

it('does not restrict guests', function (): void {
    Branch::factory()->create(['code' => 'AAA']);

    expect(Warehouse::query()->pluck('code')->all())->toContain('AAA-SF');
});

it('blocks restricted staff from writing to another branch', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));

    expect(fn () => Warehouse::factory()->create(['branch_id' => $otherBranch->id]))
        ->toThrow(BranchAccessDeniedException::class);
});

it('lets restricted staff write to their own branch', function (): void {
    $ownBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));

    $warehouse = Warehouse::factory()->create(['branch_id' => $ownBranch->id, 'code' => 'OWN-EXTRA']);

    expect($warehouse->exists)->toBeTrue();
});

it('blocks restricted staff from creating central warehouses', function (): void {
    $ownBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));

    expect(fn () => Warehouse::factory()->central()->create())
        ->toThrow(BranchAccessDeniedException::class);
});

it('lets all-branch users create central warehouses', function (): void {
    $headOfficeUser = User::factory()->withAllBranchAccess()->create();
    $headOfficeUser->assignRole(RoleName::HeadOfficeAdmin->value);

    $this->actingAs($headOfficeUser);

    $centralWarehouse = Warehouse::factory()->central()->create();

    expect($centralWarehouse->isCentral())->toBeTrue();
});

it('defaults restricted staff to their first branch and lets them switch only among their own', function (): void {
    $branchA = Branch::factory()->create(['code' => 'AAA']);
    $branchB = Branch::factory()->create(['code' => 'BBB']);
    $branchC = Branch::factory()->create(['code' => 'CCC']);

    $this->actingAs(createBranchStaff($branchA, $branchB));

    $branchContext = app(BranchContext::class);

    expect($branchContext->activeBranchId())->toBe($branchA->id);
    expect(Warehouse::query()->pluck('code')->all())->toContain('AAA-SF')->not->toContain('BBB-SF');

    $branchContext->setActiveBranch($branchB->id);

    expect(Warehouse::query()->pluck('code')->all())->toContain('BBB-SF')->not->toContain('AAA-SF');
    expect(fn () => $branchContext->setActiveBranch($branchC->id))
        ->toThrow(BranchAccessDeniedException::class);
});

it('lets a system job lift the restriction temporarily and restores it afterwards', function (): void {
    $branchA = Branch::factory()->create(['code' => 'AAA']);
    Branch::factory()->create(['code' => 'BBB']);

    $this->actingAs(createBranchStaff($branchA));

    $branchContext = app(BranchContext::class);

    $codesInsideJob = $branchContext->runUnrestricted(
        fn (): array => Warehouse::query()->pluck('code')->all()
    );

    expect($codesInsideJob)->toContain('AAA-SF', 'BBB-SF');
    expect(Warehouse::query()->pluck('code')->all())->not->toContain('BBB-SF');
});

it('refuses to run code for a branch the user cannot access', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));

    $branchContext = app(BranchContext::class);

    expect(fn () => $branchContext->runForBranch($otherBranch->id, fn () => null))
        ->toThrow(BranchAccessDeniedException::class);
});

it('can still create a branch (and its default warehouse) as a restricted user', function (): void {
    $ownBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));

    // The default warehouse of a newly created branch is system-managed.
    $newBranch = Branch::factory()->create(['code' => 'NEW-1']);

    expect(Warehouse::withoutBranchScope()->where('code', 'NEW-1-SF')->exists())->toBeTrue();
    expect($newBranch->exists)->toBeTrue();
});
