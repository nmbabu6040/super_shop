<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Enums\WarehouseType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Business rules for warehouses. Branch isolation itself is enforced by the Warehouse model
 * (BelongsToBranch): writing to a branch the actor cannot access is refused there as well.
 */
final class WarehouseService
{
    /**
     * @param  array<string, mixed>  $data  Validated data: name, code, type, branch_id, flags, contact.
     */
    public function create(array $data): Warehouse
    {
        $isCentral = $data['type'] === WarehouseType::Central->value;

        $warehouse = new Warehouse();
        $warehouse->fill([
            ...$data,
            // A central warehouse belongs to no single branch.
            'branch_id' => $isCentral ? null : $data['branch_id'],
            // Only the system-managed shop-floor warehouse of a branch is a "default" warehouse.
            'default_for_branch_id' => null,
        ]);
        $warehouse->save();

        return $warehouse;
    }

    /**
     * @param  array<string, mixed>  $data  Code, type and branch are never updated.
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $data = Arr::except($data, ['code', 'type', 'branch_id', 'default_for_branch_id']);

        if ($warehouse->default_for_branch_id !== null) {
            $wantsInactive = array_key_exists('is_active', $data) && ! $data['is_active'];
            $wantsUnsellable = array_key_exists('is_sellable', $data) && ! $data['is_sellable'];

            if ($wantsInactive || $wantsUnsellable) {
                throw BusinessRuleException::translated('admin.warehouses.cannot_change_default');
            }
        }

        $warehouse->fill($data)->save();

        return $warehouse;
    }

    public function delete(Warehouse $warehouse): void
    {
        if ($warehouse->default_for_branch_id !== null) {
            throw BusinessRuleException::translated('admin.warehouses.cannot_delete_default');
        }

        DB::transaction(static fn (): ?bool => $warehouse->delete());
    }

    public function restore(Warehouse $warehouse): void
    {
        if (
            $warehouse->branch_id !== null
            && Branch::onlyTrashed()->whereKey($warehouse->branch_id)->exists()
        ) {
            throw BusinessRuleException::translated('admin.warehouses.cannot_restore_deleted_branch');
        }

        $warehouse->restore();
    }
}
