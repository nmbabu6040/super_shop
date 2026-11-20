<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    seedRbac();
});

it('allows viewAny with the view permission or the manage permission only', function (): void {
    $policy = makeWarehousePolicy();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('warehouses.view');

    $manager = User::factory()->create();
    $manager->givePermissionTo('warehouses.manage');

    $outsider = User::factory()->create();

    expect($policy->viewAny($viewer))->toBeTrue();
    expect($policy->viewAny($manager))->toBeTrue();
    expect($policy->viewAny($outsider))->toBeFalse();
});

it('limits viewing a record to the branches the user can access', function (): void {
    $policy = makeWarehousePolicy();

    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $centralWarehouse = Warehouse::factory()->central()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('warehouses.view');
    $user->branches()->attach($ownBranch);

    expect($policy->view($user, $ownBranch->defaultWarehouse))->toBeTrue();
    expect($policy->view($user, $otherBranch->defaultWarehouse))->toBeFalse();
    // Central (shared) records are readable by anyone holding the permission.
    expect($policy->view($user, $centralWarehouse))->toBeTrue();
});

it('allows changing a central warehouse only with all-branch access', function (): void {
    $policy = makeWarehousePolicy();
    $centralWarehouse = Warehouse::factory()->central()->create();

    $branchStaff = User::factory()->create();
    $branchStaff->givePermissionTo('warehouses.update');

    $headOfficeStaff = User::factory()->withAllBranchAccess()->create();
    $headOfficeStaff->givePermissionTo('warehouses.update');

    expect($policy->update($branchStaff, $centralWarehouse))->toBeFalse();
    expect($policy->update($headOfficeStaff, $centralWarehouse))->toBeTrue();
});

it('refuses writes to another branch even with the update permission', function (): void {
    $policy = makeWarehousePolicy();

    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('warehouses.update');
    $user->branches()->attach($ownBranch);

    expect($policy->update($user, $ownBranch->defaultWarehouse))->toBeTrue();
    expect($policy->update($user, $otherBranch->defaultWarehouse))->toBeFalse();
});

it('lets the super administrator through the gate regardless of the policy', function (): void {
    $otherBranch = Branch::factory()->create();

    $superAdmin = User::factory()->withAllBranchAccess()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    expect($superAdmin->can('update', $otherBranch->defaultWarehouse))->toBeTrue();
});
