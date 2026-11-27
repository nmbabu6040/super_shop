<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockCost;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Settings\SettingsService;
use App\Support\Inventory\StockChange;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes. Every change
 *  - runs in a transaction and writes the level AND an immutable ledger row together;
 *  - locks the (warehouse, variant) cost row first, so concurrent changes of the same item
 *    queue up instead of overwriting each other;
 *  - keeps the weighted-average cost: stock added at a cost moves the average, stock removed
 *    leaves at the current average;
 *  - refuses to go below zero (except for non-batch products when the setting allows it),
 *    to receive or sell expired stock, and to move stock without the batch a product requires.
 */
final class StockService
{
    private const QUANTITY_PATTERN = '/^-?\d{1,10}(\.\d{1,4})?$/';

    private const COST_PATTERN = '/^\d{1,10}(\.\d{1,4})?$/';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function apply(StockChange $change): StockMovement
    {
        return DB::transaction(fn (): StockMovement => $this->applyChange($change));
    }

    /**
     * Applies several changes in one transaction: all of them, or none. The changes are processed
     * in a fixed order (warehouse, variant, batch) so two documents can never lock each other.
     *
     * @param  list<StockChange>  $changes
     * @return list<StockMovement>
     */
    public function applyMany(array $changes): array
    {
        $orderedChanges = array_values($changes);

        usort($orderedChanges, static fn (StockChange $first, StockChange $second): int => [$first->warehouseId, $first->variantId, $first->batchId ?? 0]
            <=> [$second->warehouseId, $second->variantId, $second->batchId ?? 0]);

        return DB::transaction(fn (): array => array_map(
            fn (StockChange $change): StockMovement => $this->applyChange($change),
            $orderedChanges
        ));
    }

    private function applyChange(StockChange $change): StockMovement
    {
        $quantity = $this->normalizeQuantity($change->quantity);
        $direction = bccomp($quantity, '0', 4);

        if ($direction === 0) {
            throw BusinessRuleException::translated('inventory.errors.zero_quantity');
        }

        if ($direction !== $change->type->direction()) {
            throw BusinessRuleException::translated('inventory.errors.direction_mismatch', ['type' => $change->type->label()]);
        }

        // Looked up across branches on purpose: central warehouses belong to no branch. Whether the
        // actor may WRITE to this warehouse's branch is enforced by the stock models themselves.
        $warehouse = Warehouse::withoutBranchScope()->find($change->warehouseId);

        if ($warehouse === null) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_missing');
        }

        if (! $warehouse->is_active) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_inactive', ['warehouse' => $warehouse->name]);
        }

        $variant = ProductVariant::query()->with('product')->find($change->variantId);

        if ($variant === null) {
            throw BusinessRuleException::translated('inventory.errors.variant_missing');
        }

        $product = $variant->product;
        $batch = $this->resolveBatch($change, $variant, $product, $direction);

        // 1. The cost row is the lock for every movement of this variant in this warehouse.
        StockCost::query()->insertOrIgnore([[
            'warehouse_id' => $warehouse->getKey(),
            'variant_id' => $variant->getKey(),
            'average_cost' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $costRow = StockCost::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('variant_id', $variant->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $onHandBefore = (string) DB::table('stock_levels')
            ->where('warehouse_id', $warehouse->getKey())
            ->where('variant_id', $variant->getKey())
            ->selectRaw('COALESCE(SUM(quantity), 0) as total')
            ->value('total');

        // 2. Cost: incoming stock moves the weighted average, outgoing stock leaves at the average.
        if ($direction > 0) {
            $incomingCost = $this->normalizeCost($change->unitCost);

            // Negative stock (when allowed) counts as nothing, so the new average is the incoming cost.
            $baseQuantity = bccomp($onHandBefore, '0', 4) === 1 ? $onHandBefore : '0';
            $newTotalQuantity = bcadd($baseQuantity, $quantity, 4);
            $weightedValue = bcadd(
                bcmul($baseQuantity, (string) $costRow->average_cost, 8),
                bcmul($quantity, $incomingCost, 8),
                8
            );

            $costRow->average_cost = Decimal::round(bcdiv($weightedValue, $newTotalQuantity, 8), 4);
            $costRow->save();

            $unitCost = $incomingCost;
        } else {
            $unitCost = bcadd((string) $costRow->average_cost, '0', 4);
        }

        // 3. The stock level of this exact batch.
        $batchKey = $batch?->getKey() ?? 0;

        StockLevel::query()->insertOrIgnore([[
            'warehouse_id' => $warehouse->getKey(),
            'branch_id' => $warehouse->branch_id,
            'variant_id' => $variant->getKey(),
            'batch_id' => $batch?->getKey(),
            'batch_key' => $batchKey,
            'quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $level = StockLevel::withoutBranchScope()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('variant_id', $variant->getKey())
            ->where('batch_key', $batchKey)
            ->lockForUpdate()
            ->firstOrFail();

        $newQuantity = bcadd((string) $level->quantity, $quantity, 4);

        if (bccomp($newQuantity, '0', 4) === -1 && ! $this->mayGoNegative($product)) {
            throw BusinessRuleException::translated('inventory.errors.insufficient_stock', [
                'available' => (string) $level->quantity,
                'requested' => ltrim($quantity, '-'),
            ]);
        }

        // Saving checks that the actor may change stock of this warehouse's branch.
        $level->quantity = $newQuantity;
        $level->save();

        return StockMovement::query()->create([
            'warehouse_id' => $warehouse->getKey(),
            'branch_id' => $warehouse->branch_id,
            'variant_id' => $variant->getKey(),
            'batch_id' => $batch?->getKey(),
            'movement_type' => $change->type->value,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => Decimal::round(bcmul($quantity, $unitCost, 8), 2),
            'balance_after' => $newQuantity,
            'source_type' => $change->sourceType,
            'source_id' => $change->sourceId,
            'note' => $change->note === null ? null : mb_substr($change->note, 0, 255),
            'created_by' => Auth::id(),
            'occurred_at' => $change->occurredAt ?? now(),
        ]);
    }

    /**
     * A product that tracks batches always needs one; the batch must belong to the variant and
     * must not be expired when stock is received, sold or transferred.
     */
    private function resolveBatch(StockChange $change, ProductVariant $variant, Product $product, int $direction): ?StockBatch
    {
        if (! $product->track_batches) {
            if ($change->batchId !== null) {
                throw BusinessRuleException::translated('inventory.errors.batch_not_tracked');
            }

            return null;
        }

        if ($change->batchId === null) {
            throw BusinessRuleException::translated('inventory.errors.batch_required');
        }

        $batch = StockBatch::query()->find($change->batchId);

        if ($batch === null) {
            throw BusinessRuleException::translated('inventory.errors.batch_missing');
        }

        if ((int) $batch->variant_id !== (int) $variant->getKey()) {
            throw BusinessRuleException::translated('inventory.errors.batch_mismatch');
        }

        if (! $product->track_expiry) {
            return $batch;
        }

        $today = CarbonImmutable::today();

        if ($direction > 0) {
            if ($batch->expiry_date === null) {
                throw BusinessRuleException::translated('inventory.errors.expiry_required', ['batch' => $batch->batch_number]);
            }

            if ($batch->isExpired($today)) {
                throw BusinessRuleException::translated('inventory.errors.cannot_receive_expired', ['batch' => $batch->batch_number]);
            }
        } elseif ($change->type->blocksExpiredStock() && $batch->isExpired($today)) {
            throw BusinessRuleException::translated('inventory.errors.batch_expired', ['batch' => $batch->batch_number]);
        }

        return $batch;
    }

    /**
     * Negative stock is only ever possible for a product without batches, and only when the
     * "allow negative stock" setting is on. Medicines and other batch products never go negative.
     */
    private function mayGoNegative(Product $product): bool
    {
        return ! $product->track_batches
            && filter_var($this->settings->get('inventory', 'allow_negative_stock', false), FILTER_VALIDATE_BOOL);
    }

    private function normalizeQuantity(string $quantity): string
    {
        if (preg_match(self::QUANTITY_PATTERN, $quantity) !== 1) {
            throw BusinessRuleException::translated('inventory.errors.invalid_quantity', ['quantity' => $quantity]);
        }

        return bcadd($quantity, '0', 4);
    }

    private function normalizeCost(?string $unitCost): string
    {
        if ($unitCost === null) {
            throw BusinessRuleException::translated('inventory.errors.cost_required');
        }

        if (preg_match(self::COST_PATTERN, $unitCost) !== 1) {
            throw BusinessRuleException::translated('inventory.errors.invalid_cost', ['cost' => $unitCost]);
        }

        return bcadd($unitCost, '0', 4);
    }
}
