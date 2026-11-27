<?php

declare(strict_types=1);

namespace App\Support\Inventory;

use App\Enums\StockMovementType;
use Carbon\CarbonInterface;

/**
 * One requested change of stock. Business modules (adjustments, purchases, sales, transfers,
 * counts) describe what happened with a StockChange and hand it to the StockService.
 */
final class StockChange
{
    /**
     * @param  string  $quantity  Signed decimal string (up to 4 decimals): positive adds, negative removes.
     * @param  string|null  $unitCost  Cost per base unit. Required when stock is added; ignored when it is
     *                                 removed (the warehouse's average cost is used).
     */
    public function __construct(
        public readonly int $warehouseId,
        public readonly int $variantId,
        public readonly StockMovementType $type,
        public readonly string $quantity,
        public readonly ?int $batchId = null,
        public readonly ?string $unitCost = null,
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        public readonly ?string $note = null,
        public readonly ?CarbonInterface $occurredAt = null,
    ) {
    }
}
