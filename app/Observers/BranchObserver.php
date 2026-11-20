<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Support\Branch\BranchContext;

class BranchObserver
{
    /**
     * Every branch gets its default shop-floor warehouse automatically, so no branch
     * can exist without a place to hold and sell stock from.
     *
     * The default warehouse is a system-managed consequence of a branch creation that was
     * already authorized, so it is created outside branch restrictions. (BranchContext is
     * resolved here, not injected, because observers live for the whole process.)
     */
    public function created(Branch $branch): void
    {
        app(BranchContext::class)->runUnrestricted(function () use ($branch): void {
            $branch->warehouses()->create([
                'name' => $branch->name . ' - Shop Floor',
                'code' => $branch->code . '-SF',
                'type' => WarehouseType::ShopFloor,
                'is_sellable' => true,
                'is_active' => true,
                'default_for_branch_id' => $branch->getKey(),
            ]);
        });
    }
}
