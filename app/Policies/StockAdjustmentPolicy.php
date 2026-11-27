<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Stock adjustments follow the "stock_adjustments" permissions plus branch access
 * (approve and reject come from ModulePolicy). There is no update or delete: documents are final.
 */
class StockAdjustmentPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'stock_adjustments';
    }
}
