<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

/**
 * Journal entries follow the "journal_entries" permissions plus branch access.
 * There is no update or delete ability on purpose: posted entries are immutable.
 */
class JournalEntryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'journal_entries';
    }

    /**
     * Reversing a posted entry is as sensitive as approving one.
     */
    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->hasModulePermission($user, 'approve') && $this->canWriteRecord($user, $entry);
    }

    public function viewReports(User $user): bool
    {
        return $user->hasAnyPermission(['reports.financial']);
    }

    public function exportReports(User $user): bool
    {
        return $user->hasAnyPermission(['reports.financial']) && $user->hasAnyPermission(['reports.export']);
    }
}
