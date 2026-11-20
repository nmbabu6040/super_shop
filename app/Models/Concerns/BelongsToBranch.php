<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use App\Support\Branch\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a model automatic branch isolation:
 *  - reads are filtered by BranchScope;
 *  - saving or deleting a record of a branch the actor cannot access is refused;
 *  - new records of restricted staff inherit the active branch when none is set
 *    (not done for models that allow shared/central records).
 *
 * Raw queries (DB::table) bypass this trait. Use Eloquent for branch data.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        static::creating(static function (Model $model): void {
            if ($model->getAttribute($model->getBranchColumn()) !== null || $model::allowsSharedBranchRecords()) {
                return;
            }

            $activeBranchId = app(BranchContext::class)->activeBranchId();

            if ($activeBranchId !== null) {
                $model->setAttribute($model->getBranchColumn(), $activeBranchId);
            }
        });

        static::saving(static function (Model $model): void {
            app(BranchContext::class)->assertCanWriteToBranch(self::branchIdOf($model));
        });

        static::deleting(static function (Model $model): void {
            app(BranchContext::class)->assertCanWriteToBranch(self::branchIdOf($model));
        });
    }

    public function getBranchColumn(): string
    {
        return 'branch_id';
    }

    /**
     * Whether rows with a NULL branch (central/shared records) exist and are visible to every branch.
     */
    public static function allowsSharedBranchRecords(): bool
    {
        return false;
    }

    /**
     * Query without branch isolation. Only for explicitly authorized code paths
     * (e.g. storefront stock availability), never for staff screens.
     *
     * @return Builder<static>
     */
    public static function withoutBranchScope(): Builder
    {
        return static::withoutGlobalScope(BranchScope::class);
    }

    private static function branchIdOf(Model $model): ?int
    {
        $branchId = $model->getAttribute($model->getBranchColumn());

        return $branchId === null ? null : (int) $branchId;
    }
}
