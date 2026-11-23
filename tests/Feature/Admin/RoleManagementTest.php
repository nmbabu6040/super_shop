<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

function createRoleSuperAdmin(): User
{
    return createStaffWithRole(RoleName::SuperAdmin, ['has_all_branch_access' => true]);
}

/**
 * A branch manager who may manage roles (this is not part of the default role).
 */
function createLimitedRoleManager(): User
{
    $manager = createStaffWithRole(RoleName::BranchManager);
    $manager->givePermissionTo(['roles.view', 'roles.create', 'roles.update', 'roles.delete']);

    return $manager;
}

it('lets a super administrator create a custom role with permissions', function (): void {
    $this->actingAs(createRoleSuperAdmin())
        ->post('/admin/roles', ['name' => 'shift-supervisor', 'permissions' => ['pos.view', 'pos.create']])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('shift-supervisor');

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['pos.create', 'pos.view']);
    expect(DB::table((new Activity())->getTable())->where('description', 'role_created')->exists())->toBeTrue();
});

it('rejects invalid or reserved role names', function (string $name): void {
    $this->actingAs(createRoleSuperAdmin())
        ->post('/admin/roles', ['name' => $name, 'permissions' => []])
        ->assertSessionHasErrors('name');
})->with([
    'spaces' => ['has space'],
    'symbols' => ['bad_name!'],
    'double hyphen' => ['bad--name'],
    'built-in name' => ['super-admin'],
    'another built-in name' => ['cashier'],
    'empty' => [''],
]);

it('lets a view-only administrator read roles but not change them', function (): void {
    $headOfficeAdmin = createStaffWithRole(RoleName::HeadOfficeAdmin, ['has_all_branch_access' => true]);

    $this->actingAs($headOfficeAdmin)->get('/admin/roles')->assertOk();
    $this->actingAs($headOfficeAdmin)->post('/admin/roles', ['name' => 'sneaky-role', 'permissions' => []])->assertForbidden();
    $this->actingAs($headOfficeAdmin)->get(route('admin.roles.edit', Role::findByName('cashier')))->assertForbidden();
});

it('stops a role manager from granting permissions they do not hold', function (): void {
    $manager = createLimitedRoleManager();

    $this->actingAs($manager)
        ->post('/admin/roles', ['name' => 'too-powerful', 'permissions' => ['accounts.manage']])
        ->assertSessionHas('error');

    expect(Role::query()->where('name', 'too-powerful')->exists())->toBeFalse();

    $this->actingAs($manager)
        ->post('/admin/roles', ['name' => 'within-limits', 'permissions' => ['pos.create']])
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::query()->where('name', 'within-limits')->exists())->toBeTrue();
});

it('keeps permissions the editor cannot grant exactly as they are', function (): void {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->givePermissionTo(['accounts.view', 'pos.create']);

    $manager = createLimitedRoleManager();

    // The manager does not hold accounts.view, so it must survive the edit.
    $this->actingAs($manager)
        ->put(route('admin.roles.update', $role), ['name' => 'auditor', 'permissions' => ['pos.create', 'pos.view']])
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['accounts.view', 'pos.create', 'pos.view']);

    // Permissions the manager CAN grant can be removed again.
    $this->actingAs($manager)
        ->put(route('admin.roles.update', $role), ['name' => 'auditor', 'permissions' => ['pos.view']]);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['accounts.view', 'pos.view']);
});

it('writes permission changes to the audit log with what was added and removed', function (): void {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->givePermissionTo(['pos.create']);

    $this->actingAs(createRoleSuperAdmin())
        ->put(route('admin.roles.update', $role), ['name' => 'auditor', 'permissions' => ['pos.view']]);

    $entry = Activity::query()->where('description', 'permissions_changed')->latest('id')->firstOrFail();

    expect($entry->properties->toArray())->toBe([
        'added' => ['pos.view'],
        'removed' => ['pos.create'],
    ]);
});

it('applies permission changes immediately', function (): void {
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $staffUser = createStaffWithRole(RoleName::Cashier);
    $staffUser->assignRole('auditor');

    expect($staffUser->can('accounts.view'))->toBeFalse();

    $this->actingAs(createRoleSuperAdmin())
        ->put(route('admin.roles.update', $role), ['name' => 'auditor', 'permissions' => ['accounts.view']]);

    expect($staffUser->fresh()->can('accounts.view'))->toBeTrue();
});

it('renames custom roles but never built-in ones', function (): void {
    $customRole = Role::create(['name' => 'old-name', 'guard_name' => 'web']);
    $superAdmin = createRoleSuperAdmin();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', $customRole), ['name' => 'new-name', 'permissions' => []]);

    expect(Role::query()->where('name', 'new-name')->exists())->toBeTrue();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', Role::findByName('cashier')), ['name' => 'hacked-name', 'permissions' => ['pos.view']]);

    expect(Role::query()->where('name', 'hacked-name')->exists())->toBeFalse();
    expect(Role::query()->where('name', 'cashier')->exists())->toBeTrue();
});

it('locks the super-admin role and keeps customers free of permissions', function (): void {
    $superAdmin = createRoleSuperAdmin();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', Role::findByName('super-admin')), ['permissions' => []])
        ->assertSessionHas('error');

    expect(Role::findByName('super-admin')->permissions()->count())->toBeGreaterThan(100);

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', Role::findByName('customer')), ['permissions' => ['pos.view']])
        ->assertSessionHas('error');

    expect(Role::findByName('customer')->permissions()->count())->toBe(0);
});

it('deletes only unused custom roles', function (): void {
    $superAdmin = createRoleSuperAdmin();

    $unusedRole = Role::create(['name' => 'unused-role', 'guard_name' => 'web']);
    $usedRole = Role::create(['name' => 'used-role', 'guard_name' => 'web']);
    createStaffWithRole(RoleName::Cashier)->assignRole('used-role');

    $this->actingAs($superAdmin)
        ->delete(route('admin.roles.destroy', $unusedRole))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::query()->where('name', 'unused-role')->exists())->toBeFalse();

    $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $usedRole))->assertSessionHas('error');
    $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', Role::findByName('cashier')))->assertSessionHas('error');

    expect(Role::query()->where('name', 'used-role')->exists())->toBeTrue();
    expect(Role::query()->where('name', 'cashier')->exists())->toBeTrue();
});

it('stops a role manager from cutting off their own access', function (): void {
    $accessRole = Role::create(['name' => 'role-admins', 'guard_name' => 'web']);
    $accessRole->givePermissionTo(['roles.view', 'roles.update']);

    $actor = createStaffWithRole(RoleName::Cashier);
    $actor->assignRole('role-admins');

    $this->actingAs($actor)
        ->put(route('admin.roles.update', $accessRole), ['name' => 'role-admins', 'permissions' => ['roles.view']])
        ->assertSessionHas('error');

    expect($accessRole->fresh()->hasPermissionTo('roles.update'))->toBeTrue();
});
