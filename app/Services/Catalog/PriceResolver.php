<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\ProductVariant;
use App\Models\VariantBranchPrice;

/**
 * Decides the selling price of a variant in a branch: the branch price when one is set,
 * otherwise the variant's normal selling price. Used by the POS and the online shop.
 */
final class PriceResolver
{
    /**
     * @return string Exact decimal string, e.g. "95.00".
     */
    public function sellingPrice(ProductVariant $variant, ?int $branchId = null): string
    {
        if ($branchId !== null) {
            // Explicit branch lookup: the caller names the branch, so the actor's focus must not hide it.
            $branchPrice = VariantBranchPrice::withoutBranchScope()
                ->where('variant_id', $variant->getKey())
                ->where('branch_id', $branchId)
                ->value('selling_price');

            if ($branchPrice !== null) {
                return (string) $branchPrice;
            }
        }

        return (string) $variant->selling_price;
    }
}
