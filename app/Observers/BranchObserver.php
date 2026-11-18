<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\WarehouseType;
use App\Models\Branch;

class BranchObserver
{
    /**
     * Every branch gets its default shop-floor warehouse automatically, so no branch
     * can exist without a place to hold and sell stock from.
     */
    public function created(Branch $branch): void
    {
        $branch->warehouses()->create([
            'name' => $branch->name . ' - Shop Floor',
            'code' => $branch->code . '-SF',
            'type' => WarehouseType::ShopFloor,
            'is_sellable' => true,
            'is_active' => true,
            'default_for_branch_id' => $branch->getKey(),
        ]);
    }
}
