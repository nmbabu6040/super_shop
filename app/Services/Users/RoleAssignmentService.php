<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Prevents privilege escalation: nobody can hand out a role holding permissions
 * they do not hold themselves. Only a super administrator can assign the super-admin role.
 */
final class RoleAssignmentService
{
    /**
     * Staff roles the actor may assign (the customer role is never a staff role).
     *
     * @return list<string>
     */
    public function assignableFor(User $actor): array
    {
        $staffRoles = Role::query()
            ->where('name', '!=', RoleName::Customer->value)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        if ($actor->isSuperAdmin()) {
            return $staffRoles->pluck('name')->all();
        }

        $actorPermissionNames = $actor->getAllPermissions()->pluck('name')->all();

        return $staffRoles
            ->reject(static fn (Role $role): bool => $role->name === RoleName::SuperAdmin->value)
            ->filter(static fn (Role $role): bool => array_diff(
                $role->permissions->pluck('name')->all(),
                $actorPermissionNames
            ) === [])
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $requestedRoleNames
     * @return list<string> Sorted, unique role names.
     *
     * @throws BusinessRuleException
     */
    public function assertAssignable(User $actor, array $requestedRoleNames): array
    {
        $requestedRoleNames = array_values(array_unique($requestedRoleNames));

        if (array_diff($requestedRoleNames, $this->assignableFor($actor)) !== []) {
            throw BusinessRuleException::translated('users.role_not_assignable');
        }

        sort($requestedRoleNames);

        return $requestedRoleNames;
    }
}
