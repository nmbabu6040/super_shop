<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may manage whom. Besides the permission, the target must be a member of staff the
 * actor "outranks": holding no permissions, branch access or super-admin status the actor lacks.
 * Nobody manages their own account here (that belongs to a profile page).
 */
class UserPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'users';
    }

    public function view(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'view') && $this->canManageStaff($user, $record, true);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'update') && $this->canManageStaff($user, $record, false);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'delete') && $this->canManageStaff($user, $record, false);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'restore') && $this->canManageStaff($user, $record, false);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'force-delete') && $this->canManageStaff($user, $record, false);
    }

    public function suspend(User $user, Model $record): bool
    {
        return $this->update($user, $record) && $this->hasStatus($record, UserStatus::Active);
    }

    public function reactivate(User $user, Model $record): bool
    {
        return $this->update($user, $record) && $this->hasStatus($record, UserStatus::Suspended);
    }

    public function sendInvitation(User $user, Model $record): bool
    {
        return $this->update($user, $record) && $this->hasStatus($record, UserStatus::Pending);
    }

    public function sendPasswordReset(User $user, Model $record): bool
    {
        return $this->update($user, $record) && $this->hasStatus($record, UserStatus::Active);
    }

    private function hasStatus(Model $record, UserStatus $status): bool
    {
        return $record instanceof User && $record->status === $status;
    }

    private function canManageStaff(User $actor, Model $target, bool $allowSelf): bool
    {
        if (! $target instanceof User) {
            return false;
        }

        if ($actor->is($target)) {
            return $allowSelf;
        }

        // Customers are managed in the customer (CRM) area, not here.
        if ($target->isCustomerOnly()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        if ($target->has_all_branch_access && ! $actor->has_all_branch_access) {
            return false;
        }

        $targetPermissionNames = $target->getAllPermissions()->pluck('name')->all();
        $actorPermissionNames = $actor->getAllPermissions()->pluck('name')->all();

        if (array_diff($targetPermissionNames, $actorPermissionNames) !== []) {
            return false;
        }

        if ($actor->has_all_branch_access) {
            return true;
        }

        $targetBranchIds = $target->relationLoaded('branches')
            ? $target->branches->pluck('id')->all()
            : $target->branches()->pluck('branches.id')->all();

        $actorBranchIds = $actor->branches()->pluck('branches.id')->all();

        return array_diff($targetBranchIds, $actorBranchIds) === [];
    }
}
