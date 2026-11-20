<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Warehouses carry a branch_id, so the generic permission + branch checks of ModulePolicy
 * already apply (central warehouses are readable by all, writable only with all-branch access).
 */
class WarehousePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'warehouses';
    }
}
