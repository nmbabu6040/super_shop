<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON search of product variants for the line editors of stock documents (adjustments now,
 * transfers and counts later): by name, SKU or barcode.
 */
class VariantSearchController extends Controller
{
    private const RESULT_LIMIT = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('viewAny', Product::class) || $user->can('create', StockAdjustment::class), 403);

        $searchTerm = trim((string) $request->query('q', ''));

        if (mb_strlen($searchTerm) < 2) {
            return response()->json([]);
        }

        // % and _ typed by the user are text, not wildcards.
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($searchTerm, 0, 60)) . '%';

        $variants = ProductVariant::query()
            ->with(['product.unit', 'primaryBarcode'])
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->where(function (Builder $query) use ($like): void {
                $query->where('sku', 'like', $like)
                    ->orWhereHas('barcodes', fn (Builder $barcodeQuery) => $barcodeQuery->where('barcode', 'like', $like))
                    ->orWhereHas('product', function (Builder $productQuery) use ($like): void {
                        $productQuery->where('name->en', 'like', $like)->orWhere('name->bn', 'like', $like);
                    });
            })
            ->orderBy('sku')
            ->limit(self::RESULT_LIMIT)
            ->get();

        $warehouseId = (int) $request->query('warehouse_id', 0);
        $onHandByVariant = $warehouseId > 0 && $variants->isNotEmpty()
            ? StockLevel::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('variant_id', $variants->pluck('id'))
            ->selectRaw('variant_id, SUM(quantity) as total')
            ->groupBy('variant_id')
            ->pluck('total', 'variant_id')
            : collect();

        return response()->json($variants->map(fn (ProductVariant $variant): array => [
            'id' => $variant->getKey(),
            'sku' => $variant->sku,
            'label' => $variant->product->name . ($variant->options ? ' — ' . implode(' / ', $variant->options) : ''),
            'barcode' => $variant->primaryBarcode?->barcode,
            'unit' => $variant->product->unit?->symbol,
            'track_batches' => (bool) $variant->product->track_batches,
            'track_expiry' => (bool) $variant->product->track_expiry,
            'on_hand' => $warehouseId > 0 ? bcadd((string) ($onHandByVariant[$variant->getKey()] ?? '0'), '0', 4) : '',
        ])->values());
    }
}
