<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\StockLevel;
use Carbon\CarbonImmutable;

/**
 * Read side of the stock: quantities on hand and first-expiry-first-out allocation.
 */
final class StockQueryService
{
    /**
     * Quantity on hand of a variant in one warehouse, or in every warehouse the actor can see.
     */
    public function onHand(int $variantId, ?int $warehouseId = null): string
    {
        $total = StockLevel::query()
            ->where('variant_id', $variantId)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(quantity), 0) as total')
            ->value('total');

        return bcadd((string) $total, '0', 4);
    }

    /**
     * Splits a quantity over the batches of a warehouse, earliest expiry first (FEFO). Batches without
     * an expiry date come last; expired batches are never used. The warehouse is named explicitly,
     * so the lookup is not narrowed by the actor's working branch.
     *
     * @return list<array{batch_id: int|null, quantity: string}>
     *
     * @throws BusinessRuleException when the sellable stock is not enough
     */
    public function allocateFefo(int $variantId, int $warehouseId, string $quantity): array
    {
        $today = CarbonImmutable::today();

        $levels = StockLevel::withoutBranchScope()
            ->with('batch')
            ->where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->get()
            ->filter(static fn (StockLevel $level): bool => $level->batch === null || ! $level->batch->isExpired($today))
            ->all();

        usort($levels, static fn (StockLevel $first, StockLevel $second): int => [
            $first->batch?->expiry_date?->format('Y-m-d') ?? '9999-12-31',
            $first->batch_id ?? 0,
        ] <=> [
            $second->batch?->expiry_date?->format('Y-m-d') ?? '9999-12-31',
            $second->batch_id ?? 0,
        ]);

        $remaining = bcadd($quantity, '0', 4);
        $allocation = [];

        foreach ($levels as $level) {
            if (bccomp($remaining, '0', 4) !== 1) {
                break;
            }

            $taken = bccomp((string) $level->quantity, $remaining, 4) === 1 ? $remaining : (string) $level->quantity;

            $allocation[] = ['batch_id' => $level->batch_id === null ? null : (int) $level->batch_id, 'quantity' => $taken];
            $remaining = bcsub($remaining, $taken, 4);
        }

        if (bccomp($remaining, '0', 4) === 1) {
            throw BusinessRuleException::translated('inventory.errors.insufficient_stock', [
                'available' => bcsub(bcadd($quantity, '0', 4), $remaining, 4),
                'requested' => bcadd($quantity, '0', 4),
            ]);
        }

        return $allocation;
    }
}
