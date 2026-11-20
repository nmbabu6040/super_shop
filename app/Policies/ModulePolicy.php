<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for module policies (Phase 5+ extends it, one policy per model).
 *
 * Authorization has two layers, both enforced on the backend:
 *  1. permission: "<module>.<action>" or "<module>.manage";
 *  2. branch: if the record carries a branch_id, the user must have access to that branch.
 *     Records without a branch_id value (shared records such as a central warehouse) are
 *     readable by anyone holding the permission, but writable only with all-branch access.
 */
abstract class ModulePolicy
{
    /**
     * The module key from config/rbac.php, e.g. "warehouses".
     */
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $this->hasModulePermission($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'view') && $this->canReadRecord($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->hasModulePermission($user, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'update') && $this->canWriteRecord($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'delete') && $this->canWriteRecord($user, $record);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'restore') && $this->canWriteRecord($user, $record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'force-delete') && $this->canWriteRecord($user, $record);
    }

    public function approve(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'approve') && $this->canWriteRecord($user, $record);
    }

    public function reject(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'reject') && $this->canWriteRecord($user, $record);
    }

    public function print(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'print') && $this->canReadRecord($user, $record);
    }

    public function export(User $user): bool
    {
        return $this->hasModulePermission($user, 'export');
    }

    protected function hasModulePermission(User $user, string $action): bool
    {
        return $user->hasAnyPermission([
            $this->module() . '.' . $action,
            $this->module() . '.manage',
        ]);
    }

    protected function canReadRecord(User $user, Model $record): bool
    {
        if (! $this->recordHasBranchColumn($record)) {
            return true;
        }

        $branchId = $record->getAttribute('branch_id');

        return $branchId === null || $user->canAccessBranch((int) $branchId);
    }

    protected function canWriteRecord(User $user, Model $record): bool
    {
        if (! $this->recordHasBranchColumn($record)) {
            return true;
        }

        $branchId = $record->getAttribute('branch_id');

        if ($branchId === null) {
            return (bool) $user->has_all_branch_access;
        }

        return $user->canAccessBranch((int) $branchId);
    }

    private function recordHasBranchColumn(Model $record): bool
    {
        return array_key_exists('branch_id', $record->getAttributes());
    }
}
