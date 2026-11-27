<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\JournalEntryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Warehouse;

/**
 * A warehouse with stock, or with adjustments waiting for approval, cannot be deleted.
 */
final class WarehouseStockGuard
{
    public function deleting(Warehouse $warehouse): void
    {
        $hasStock = StockLevel::withoutBranchScope()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_has_stock');
        }

        $hasPendingAdjustments = StockAdjustment::withoutBranchScope()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('status', JournalEntryStatus::Pending->value)
            ->exists();

        if ($hasPendingAdjustments) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_has_pending');
        }
    }
}
