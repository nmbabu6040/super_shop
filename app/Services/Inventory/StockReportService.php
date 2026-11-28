<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read side of the inventory pages. All queries go through the models, so branch isolation
 * (own branches plus the central warehouse) applies automatically.
 */
final class StockReportService
{
    public const SUMMARY_PAGE_SIZE = 30;

    public const MOVEMENT_PAGE_SIZE = 50;

    public const EXPORT_LIMIT = 10000;

    /**
     * The stock of every variant in every warehouse (all batches added up), with its reorder level and average cost.
     */
    public function summaryQuery(?int $warehouseId, string $search, ?string $status): Builder
    {
        $like = $this->likePattern($search);

        return StockLevel::query()
            ->join('product_variants as v', 'v.id', '=', 'stock_levels.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('stock_costs as c', function ($join): void {
                $join->on('c.warehouse_id', '=', 'stock_levels.warehouse_id')
                    ->on('c.variant_id', '=', 'stock_levels.variant_id');
            })
            ->whereNull('v.deleted_at')
            ->whereNull('p.deleted_at')
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('stock_levels.warehouse_id', $warehouseId))
            ->when($like !== null, fn (Builder $query) => $this->applyProductSearch($query, $like))
            ->selectRaw('stock_levels.warehouse_id, stock_levels.variant_id, p.reorder_level, COALESCE(c.average_cost, 0) as average_cost, SUM(stock_levels.quantity) as on_hand')
            ->groupBy('stock_levels.warehouse_id', 'stock_levels.variant_id', 'p.reorder_level', 'c.average_cost')
            ->when($status === 'out', fn (Builder $query) => $query->havingRaw('SUM(stock_levels.quantity) <= 0'))
            ->when($status === 'low', fn (Builder $query) => $query->havingRaw('SUM(stock_levels.quantity) > 0 AND p.reorder_level > 0 AND SUM(stock_levels.quantity) <= p.reorder_level'))
            ->when($status === 'ok', fn (Builder $query) => $query->havingRaw('SUM(stock_levels.quantity) > 0 AND (p.reorder_level <= 0 OR SUM(stock_levels.quantity) > p.reorder_level)'))
            ->orderBy('v.sku')
            ->orderBy('stock_levels.warehouse_id');
    }

    public function summary(?int $warehouseId, string $search, ?string $status): LengthAwarePaginator
    {
        return $this->summaryQuery($warehouseId, $search, $status)
            ->with(['warehouse', 'variant.product.unit'])
            ->paginate(self::SUMMARY_PAGE_SIZE)
            ->withQueryString();
    }

    /**
     * Batch-level stock with expiry dates, earliest expiry first (no expiry date last).
     */
    public function batches(?int $warehouseId, string $search, ?string $expiry, int $alertDays): LengthAwarePaginator
    {
        $like = $this->likePattern($search);
        $today = CarbonImmutable::today();
        $soonLimit = $today->addDays($alertDays)->format('Y-m-d');

        return StockLevel::query()
            ->join('stock_batches as b', 'b.id', '=', 'stock_levels.batch_id')
            ->join('product_variants as v', 'v.id', '=', 'stock_levels.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('v.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('stock_levels.quantity', '>', 0)
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('stock_levels.warehouse_id', $warehouseId))
            ->when($like !== null, fn (Builder $query) => $this->applyProductSearch($query, $like))
            ->when($expiry === 'expired', fn (Builder $query) => $query->whereDate('b.expiry_date', '<', $today->format('Y-m-d')))
            ->when($expiry === 'soon', fn (Builder $query) => $query
                ->whereDate('b.expiry_date', '>=', $today->format('Y-m-d'))
                ->whereDate('b.expiry_date', '<=', $soonLimit))
            ->when($expiry === 'ok', fn (Builder $query) => $query->where(function (Builder $okQuery) use ($soonLimit): void {
                $okQuery->whereNull('b.expiry_date')->orWhereDate('b.expiry_date', '>', $soonLimit);
            }))
            ->select('stock_levels.*')
            ->orderByRaw('b.expiry_date IS NULL')
            ->orderBy('b.expiry_date')
            ->orderBy('stock_levels.id')
            ->with(['warehouse', 'variant.product.unit', 'batch'])
            ->paginate(self::SUMMARY_PAGE_SIZE)
            ->withQueryString();
    }

    /**
     * The stock ledger, newest movement first.
     */
    public function movements(?int $warehouseId, string $search, ?StockMovementType $type, ?string $from, ?string $to): LengthAwarePaginator
    {
        $like = $this->likePattern($search);

        return StockMovement::query()
            ->with(['warehouse', 'variant.product', 'batch', 'creator'])
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->when($type !== null, fn (Builder $query) => $query->where('movement_type', $type->value))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('occurred_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('occurred_at', '<=', $to))
            ->when($like !== null, function (Builder $query) use ($like): void {
                $query->whereHas('variant', function (Builder $variantQuery) use ($like): void {
                    $variantQuery->where('sku', 'like', $like)
                        ->orWhereHas('product', fn (Builder $productQuery) => $productQuery
                            ->where('name->en', 'like', $like)
                            ->orWhere('name->bn', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(self::MOVEMENT_PAGE_SIZE)
            ->withQueryString();
    }

    /**
     * "out" (nothing left), "low" (at or below the reorder level) or "ok".
     */
    public static function statusOf(string $onHand, string $reorderLevel): string
    {
        if (bccomp($onHand, '0', 4) !== 1) {
            return 'out';
        }

        if (bccomp($reorderLevel, '0', 4) === 1 && bccomp($onHand, $reorderLevel, 4) !== 1) {
            return 'low';
        }

        return 'ok';
    }

    /**
     * Name, SKU or barcode of a product.
     */
    private function applyProductSearch(Builder $query, string $like): Builder
    {
        return $query->where(function (Builder $searchQuery) use ($like): void {
            $searchQuery->where('v.sku', 'like', $like)
                ->orWhere('p.name->en', 'like', $like)
                ->orWhere('p.name->bn', 'like', $like)
                ->orWhereExists(function (QueryBuilder $barcodeQuery) use ($like): void {
                    $barcodeQuery->selectRaw('1')
                        ->from('product_barcodes as pb')
                        ->whereColumn('pb.variant_id', 'v.id')
                        ->where('pb.barcode', 'like', $like);
                });
        });
    }

    /**
     * % and _ typed by the user are text, not wildcards.
     */
    private function likePattern(string $search): ?string
    {
        $search = trim($search);

        return $search === '' ? null : '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 60)) . '%';
    }
}
