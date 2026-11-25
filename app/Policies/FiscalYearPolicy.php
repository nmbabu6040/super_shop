<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Fiscal years and periods follow the "accounts" permissions. Closing a period needs
 * accounts.update; reopening one (which lets postings back into closed books) needs accounts.manage.
 */
class FiscalYearPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'accounts';
    }

    public function closePeriod(User $user): bool
    {
        return $this->hasModulePermission($user, 'update');
    }

    public function reopenPeriod(User $user): bool
    {
        return $user->hasAnyPermission(['accounts.manage']);
    }
}
