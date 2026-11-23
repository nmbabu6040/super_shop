<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role and permission management with the safety rules of the system:
 *  - nobody can hand out a permission they do not hold themselves (no privilege escalation);
 *  - permissions the actor cannot grant are never removed by their edit either (they are preserved);
 *  - the super-admin role is locked; the customer role can never hold permissions;
 *  - system roles cannot be renamed or deleted; a role in use cannot be deleted;
 *  - a non-super administrator cannot cut off their own access to this screen.
 * Every change is written to the audit log (Spatie does not log role changes itself).
 */
final class RoleService
{
    public function __construct(private readonly PermissionRegistrar $permissionRegistrar)
    {
    }

    public function isSystemRole(Role $role): bool
    {
        return RoleName::tryFrom($role->name) !== null;
    }

    /**
     * Permission names the actor may grant to a role.
     *
     * @return list<string>
     */
    public function grantablePermissionNames(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return Permission::query()->pluck('name')->all();
        }

        return $actor->getAllPermissions()->pluck('name')->all();
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function create(User $actor, string $name, array $permissionNames): Role
    {
        $permissionNames = array_values(array_unique($permissionNames));
        $this->assertGrantable($actor, $permissionNames);

        $role = DB::transaction(function () use ($actor, $name, $permissionNames): Role {
            $role = Role::create(['name' => $name, 'guard_name' => (string) config('rbac.guard', 'web')]);
            $role->syncPermissions($permissionNames);

            $this->log($actor, $role, 'role_created', ['permissions' => $permissionNames]);

            return $role;
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $role;
    }

    /**
     * @param  list<string>  $requestedPermissionNames  What the form submitted (only grantable ones can be changed).
     */
    public function update(User $actor, Role $role, ?string $newName, array $requestedPermissionNames): Role
    {
        $systemRole = RoleName::tryFrom($role->name);

        if ($systemRole === RoleName::SuperAdmin) {
            throw BusinessRuleException::translated('roles.super_admin_locked');
        }

        $requestedPermissionNames = array_values(array_unique($requestedPermissionNames));

        if ($systemRole === RoleName::Customer && $requestedPermissionNames !== []) {
            throw BusinessRuleException::translated('roles.customer_no_permissions');
        }

        $this->assertGrantable($actor, $requestedPermissionNames);

        $currentPermissionNames = $role->permissions()->pluck('name')->all();

        // Permissions the actor is not allowed to grant stay exactly as they are.
        $preservedPermissionNames = array_values(array_diff(
            $currentPermissionNames,
            $this->grantablePermissionNames($actor)
        ));

        $finalPermissionNames = array_values(array_unique([...$requestedPermissionNames, ...$preservedPermissionNames]));

        $this->assertNotLockingOut($actor, $role, $currentPermissionNames, $finalPermissionNames);

        DB::transaction(function () use ($actor, $role, $systemRole, $newName, $currentPermissionNames, $finalPermissionNames): void {
            // System roles keep their names: the code refers to them.
            if ($systemRole === null && $newName !== null && $newName !== $role->name) {
                $previousName = $role->name;
                $role->update(['name' => $newName]);
                $this->log($actor, $role, 'role_renamed', ['old' => $previousName, 'new' => $newName]);
            }

            $addedPermissionNames = array_values(array_diff($finalPermissionNames, $currentPermissionNames));
            $removedPermissionNames = array_values(array_diff($currentPermissionNames, $finalPermissionNames));

            if ($addedPermissionNames !== [] || $removedPermissionNames !== []) {
                $role->syncPermissions($finalPermissionNames);

                $this->log($actor, $role, 'permissions_changed', [
                    'added' => $addedPermissionNames,
                    'removed' => $removedPermissionNames,
                ]);
            }
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $role;
    }

    public function delete(User $actor, Role $role): void
    {
        if ($this->isSystemRole($role)) {
            throw BusinessRuleException::translated('roles.system_role_locked');
        }

        if ($role->users()->exists()) {
            throw BusinessRuleException::translated('roles.in_use');
        }

        DB::transaction(function () use ($actor, $role): void {
            $this->log($actor, $role, 'role_deleted', ['name' => $role->name]);
            $role->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $requestedPermissionNames
     */
    private function assertGrantable(User $actor, array $requestedPermissionNames): void
    {
        if (array_diff($requestedPermissionNames, $this->grantablePermissionNames($actor)) !== []) {
            throw BusinessRuleException::translated('roles.permission_not_grantable');
        }
    }

    /**
     * An administrator who is not a super administrator must not remove their own way back in
     * by editing the role that gives them access to role management.
     *
     * @param  list<string>  $currentPermissionNames
     * @param  list<string>  $finalPermissionNames
     */
    private function assertNotLockingOut(User $actor, Role $role, array $currentPermissionNames, array $finalPermissionNames): void
    {
        if ($actor->isSuperAdmin() || ! $actor->hasRole($role->name)) {
            return;
        }

        $accessPermissions = ['roles.update', 'roles.manage'];

        $hadAccess = array_intersect($accessPermissions, $currentPermissionNames) !== [];
        $keepsAccess = array_intersect($accessPermissions, $finalPermissionNames) !== [];

        if ($hadAccess && ! $keepsAccess) {
            throw BusinessRuleException::translated('roles.would_lock_out');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(User $actor, Role $role, string $event, array $properties): void
    {
        activity('role')
            ->causedBy($actor)
            ->performedOn($role)
            ->withProperties($properties)
            ->log($event);
    }
}
