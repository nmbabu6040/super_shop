<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Stock levels, batches and the stock ledger follow the "inventory" permissions.
 * Costs and stock values are visible only with inventory.manage.
 */
class StockLevelPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'inventory';
    }

    public function viewCosts(User $user): bool
    {
        return $user->hasAnyPermission(['inventory.manage']);
    }
}
