<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Counts what needs attention: stock at or below the reorder level and batches that expired or
 * are about to. Plain queries, limited to the given branches (null = every branch).
 */
final class StockAlertService
{
    /**
     * Number of variant/warehouse pairs at or below their product's reorder level.
     *
     * @param  list<int>|null  $branchIds
     */
    public function lowStockCount(?array $branchIds): int
    {
        return DB::table('stock_levels as l')
            ->join('product_variants as v', 'v.id', '=', 'l.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('v.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.is_active', true)
            ->where('p.reorder_level', '>', 0)
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('l.branch_id', $branchIds))
            ->groupBy('l.warehouse_id', 'l.variant_id', 'p.reorder_level')
            ->havingRaw('SUM(l.quantity) <= p.reorder_level')
            ->select('l.warehouse_id', 'l.variant_id')
            ->get()
            ->count();
    }

    /**
     * Batches (per warehouse) that still hold stock and have expired, or expire within the given days.
     *
     * @param  list<int>|null  $branchIds
     * @return array{expired: int, soon: int}
     */
    public function expiryCounts(?array $branchIds, int $days): array
    {
        $today = CarbonImmutable::today();

        $stockedBatches = static fn (): Builder => DB::table('stock_levels as l')
            ->join('stock_batches as b', 'b.id', '=', 'l.batch_id')
            ->join('product_variants as v', 'v.id', '=', 'l.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('v.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('l.quantity', '>', 0)
            ->whereNotNull('b.expiry_date')
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('l.branch_id', $branchIds));

        return [
            'expired' => $stockedBatches()->whereDate('b.expiry_date', '<', $today->format('Y-m-d'))->count(),
            'soon' => $stockedBatches()
                ->whereDate('b.expiry_date', '>=', $today->format('Y-m-d'))
                ->whereDate('b.expiry_date', '<=', $today->addDays($days)->format('Y-m-d'))
                ->count(),
        ];
    }
}
