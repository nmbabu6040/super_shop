<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Services\Rbac\RbacSyncService;
use App\Support\Rbac\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates every catalog permission and all seventeen system roles', function (): void {
    $result = app(RbacSyncService::class)->sync();

    expect(Permission::query()->count())->toBe(count(PermissionCatalog::allPermissionNames()));
    expect(Role::query()->count())->toBe(17);
    expect($result['roles_created'])->toBe(17);
});

it('is idempotent', function (): void {
    app(RbacSyncService::class)->sync();
    $secondResult = app(RbacSyncService::class)->sync();

    expect($secondResult['permissions_created'])->toBe(0);
    expect($secondResult['roles_created'])->toBe(0);
    expect(Role::query()->count())->toBe(17);
});

it('gives the super administrator every permission', function (): void {
    seedRbac();

    $superAdminRole = Role::findByName(RoleName::SuperAdmin->value);

    expect($superAdminRole->permissions()->count())->toBe(count(PermissionCatalog::allPermissionNames()));
});

it('does not undo role customisations when syncing again', function (): void {
    seedRbac();

    $cashierRole = Role::findByName(RoleName::Cashier->value);
    $cashierRole->givePermissionTo('refunds.create');
    $cashierRole->revokePermissionTo('pos.create');

    app(RbacSyncService::class)->sync();

    $cashierRole = $cashierRole->fresh();

    expect($cashierRole->hasPermissionTo('refunds.create'))->toBeTrue();
    expect($cashierRole->hasPermissionTo('pos.create'))->toBeFalse();
});

it('restores default grants only when a reset is explicitly requested', function (): void {
    seedRbac();

    $cashierRole = Role::findByName(RoleName::Cashier->value);
    $cashierRole->givePermissionTo('refunds.create');

    app(RbacSyncService::class)->sync(resetRoleGrants: true);

    $cashierRole = $cashierRole->fresh();

    expect($cashierRole->hasPermissionTo('refunds.create'))->toBeFalse();
    expect($cashierRole->hasPermissionTo('pos.create'))->toBeTrue();
});

it('grants the defaults of a newly added permission to existing roles', function (): void {
    seedRbac();

    // Simulate a permission that is new after a later phase: remove it, then sync.
    Permission::findByName('pos.open-drawer')->delete();

    app(RbacSyncService::class)->sync();

    expect(Role::findByName(RoleName::Cashier->value)->hasPermissionTo('pos.open-drawer'))->toBeTrue();
});

it('defines exactly the roles of the RoleName enum in the config', function (): void {
    $configuredRoleNames = array_keys(config('rbac.roles'));
    $enumRoleNames = array_map(static fn (RoleName $roleName): string => $roleName->value, RoleName::cases());

    expect($configuredRoleNames)->toEqualCanonicalizing($enumRoleNames);
});

it('expands every role in the config without errors', function (RoleName $roleName): void {
    expect(PermissionCatalog::permissionsForRole($roleName->value))->toBeArray();
})->with(fn (): array => array_map(static fn (RoleName $roleName): array => [$roleName], RoleName::cases()));
