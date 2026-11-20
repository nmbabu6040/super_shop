<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A branch record has no branch_id column (it IS the branch), so the generic branch check of
 * ModulePolicy does not apply. This policy checks branch access explicitly, and reserves
 * creating, deleting and restoring branches for users with access to all branches.
 */
class BranchPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'branches';
    }

    public function view(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'view') && $this->canAccessThisBranch($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->hasModulePermission($user, 'create') && $user->has_all_branch_access;
    }

    public function update(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'update') && $this->canAccessThisBranch($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'delete') && $user->has_all_branch_access;
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'restore') && $user->has_all_branch_access;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'force-delete') && $user->has_all_branch_access;
    }

    private function canAccessThisBranch(User $user, Model $record): bool
    {
        return $record instanceof Branch && $user->canAccessBranch($record);
    }
}
