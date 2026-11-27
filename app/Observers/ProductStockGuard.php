<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;

/**
 * A product that still has stock on hand cannot be deleted: its stock would disappear from every list.
 */
final class ProductStockGuard
{
    public function deleting(Product $product): void
    {
        $hasStock = StockLevel::withoutBranchScope()
            ->whereIn('variant_id', ProductVariant::withTrashed()->where('product_id', $product->getKey())->select('id'))
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            throw BusinessRuleException::translated('inventory.errors.product_has_stock');
        }
    }
}
