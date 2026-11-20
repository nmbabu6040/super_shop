<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Branch\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that limits queries to the branches the current actor may see.
 * Applied automatically to every model that uses the BelongsToBranch trait.
 */
final class BranchScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     * @param  Model  $model  A model using the BelongsToBranch trait.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $branchIds = app(BranchContext::class)->scopeBranchIds();

        if ($branchIds === null) {
            return;
        }

        if ($branchIds === []) {
            // Fail-closed: a restricted user without any branch sees nothing.
            $builder->whereRaw('0 = 1');

            return;
        }

        $branchColumn = $model->qualifyColumn($model->getBranchColumn());

        // Grouped so the OR never leaks past other conditions (e.g. soft deletes).
        $builder->where(function (Builder $branchQuery) use ($branchIds, $branchColumn, $model): void {
            $branchQuery->whereIn($branchColumn, $branchIds);

            if ($model::allowsSharedBranchRecords()) {
                $branchQuery->orWhereNull($branchColumn);
            }
        });
    }
}
