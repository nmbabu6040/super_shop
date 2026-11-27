<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\JournalEntryStatus;
use App\Enums\SystemAccount;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockBatch;
use App\Models\StockCost;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\AccountRegistry;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\JournalService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use App\Support\Inventory\StockChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stock adjustments (opening stock, damage, expiry, loss, found stock, corrections...).
 *
 * Creating one only records the request. Approving it (by another person) applies every line
 * through the stock service and posts ONE accounting entry (Inventory against the adjustment or
 * opening-balance account), all in a single transaction: either everything happens or nothing.
 */
final class StockAdjustmentService
{
    private const QUANTITY_PATTERN = '/^-?\d{1,10}(\.\d{1,4})?$/';

    private const COST_PATTERN = '/^\d{1,10}(\.\d{1,4})?$/';

    private const BATCH_PATTERN = '/^[A-Z0-9][A-Z0-9._\/-]{0,59}$/';

    private const MAX_LINES = 100;

    public function __construct(
        private readonly StockService $stockService,
        private readonly BatchService $batchService,
        private readonly JournalService $journalService,
        private readonly AccountRegistry $accountRegistry,
        private readonly DocumentNumberService $documentNumbers,
    ) {
    }

    /**
     * @param  array{warehouse_id: int|string, adjustment_date: string, reason: string, notes?: string|null, lines: array<int, array<string, mixed>>}  $data
     */
    public function create(array $data, User $creator): StockAdjustment
    {
        $reason = AdjustmentReason::tryFrom((string) ($data['reason'] ?? ''))
            ?? throw BusinessRuleException::translated('inventory.errors.invalid_reason');

        $adjustmentDate = $this->parseDate((string) ($data['adjustment_date'] ?? ''));

        if ($adjustmentDate->isAfter(CarbonImmutable::today())) {
            throw BusinessRuleException::translated('inventory.errors.future_date');
        }

        // Looked up across branches on purpose (central warehouses have no branch); whether the actor
        // may write to this warehouse's branch is enforced when the document is saved.
        $warehouse = Warehouse::withoutBranchScope()->find((int) ($data['warehouse_id'] ?? 0));

        if ($warehouse === null) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_missing');
        }

        if (! $warehouse->is_active) {
            throw BusinessRuleException::translated('inventory.errors.warehouse_inactive', ['warehouse' => $warehouse->name]);
        }

        $lines = $this->normalizeLines($reason, array_values((array) ($data['lines'] ?? [])));
        $notes = isset($data['notes']) && trim((string) $data['notes']) !== '' ? mb_substr(trim((string) $data['notes']), 0, 500) : null;

        return DB::transaction(function () use ($reason, $adjustmentDate, $warehouse, $lines, $notes, $creator): StockAdjustment {
            $adjustment = new StockAdjustment();
            $adjustment->fill([
                'adjustment_number' => $this->nextNumber($warehouse, $adjustmentDate),
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'adjustment_date' => $adjustmentDate->format('Y-m-d'),
                'reason' => $reason,
                'notes' => $notes,
            ]);
            $adjustment->forceFill([
                'status' => JournalEntryStatus::Pending,
                'created_by' => $creator->getKey(),
            ])->save();

            foreach ($lines as $index => $line) {
                StockAdjustmentLine::query()->create([
                    ...$line,
                    'stock_adjustment_id' => $adjustment->getKey(),
                    'line_number' => $index + 1,
                ]);
            }

            return $adjustment->load('lines');
        });
    }

    public function approve(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approver): StockAdjustment {
            $locked = StockAdjustment::withoutBranchScope()->lockForUpdate()->findOrFail($adjustment->getKey());

            if ($locked->status !== JournalEntryStatus::Pending) {
                throw BusinessRuleException::translated('accounting.errors.not_pending');
            }

            // Maker-checker: nobody approves their own document (a super administrator excepted).
            if ((int) $locked->created_by === (int) $approver->getKey() && ! $approver->isSuperAdmin()) {
                throw BusinessRuleException::translated('accounting.errors.self_approval');
            }

            $locked->load('lines.variant.product');

            // A fixed order (variant, batch) keeps concurrent documents from locking each other.
            $lines = $locked->lines->sortBy(static fn (StockAdjustmentLine $line): string => str_pad((string) $line->variant_id, 12, '0', STR_PAD_LEFT) . '|' . $line->batch_number)->values();

            $gainValue = '0.00';
            $lossValue = '0.00';

            foreach ($lines as $line) {
                $isGain = bccomp((string) $line->quantity_change, '0', 4) === 1;
                $variant = $line->variant;
                $batchId = null;

                if ($variant->product->track_batches) {
                    $batchId = $isGain
                        ? $this->batchService->findOrCreate($variant->getKey(), (string) $line->batch_number, $line->expiry_date?->format('Y-m-d'))->getKey()
                        : $this->existingBatchId($variant->getKey(), (string) $line->batch_number);
                }

                $movement = $this->stockService->apply(new StockChange(
                    (int) $locked->warehouse_id,
                    (int) $variant->getKey(),
                    $locked->reason->movementType($isGain),
                    (string) $line->quantity_change,
                    $batchId,
                    $isGain ? $this->gainCost($line, (int) $locked->warehouse_id) : null,
                    'stock_adjustment',
                    (int) $locked->getKey(),
                    $line->notes,
                ));

                $line->forceFill([
                    'batch_id' => $batchId,
                    'stock_movement_id' => $movement->getKey(),
                    'applied_unit_cost' => $movement->unit_cost,
                    'total_cost' => $movement->total_cost,
                ])->save();

                if ($isGain) {
                    $gainValue = bcadd($gainValue, (string) $movement->total_cost, 2);
                } else {
                    $lossValue = bcadd($lossValue, ltrim((string) $movement->total_cost, '-'), 2);
                }
            }

            $journalEntry = $this->postJournal($locked, $gainValue, $lossValue);

            $locked->forceFill([
                'status' => JournalEntryStatus::Posted,
                'reviewed_by' => $approver->getKey(),
                'reviewed_at' => now(),
                'total_value' => bcsub($gainValue, $lossValue, 2),
                'journal_entry_id' => $journalEntry?->getKey(),
            ])->save();

            return $locked;
        });
    }

    public function reject(StockAdjustment $adjustment, User $reviewer, string $reason): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reviewer, $reason): StockAdjustment {
            $locked = StockAdjustment::withoutBranchScope()->lockForUpdate()->findOrFail($adjustment->getKey());

            if ($locked->status !== JournalEntryStatus::Pending) {
                throw BusinessRuleException::translated('accounting.errors.not_pending');
            }

            $locked->forceFill([
                'status' => JournalEntryStatus::Rejected,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 255),
            ])->save();

            return $locked;
        });
    }

    /**
     * Gains are debited to Inventory and credited to the contra account; losses the other way round.
     * Nothing is posted when the adjustment has no value (for example only zero-cost stock).
     */
    private function postJournal(StockAdjustment $adjustment, string $gainValue, string $lossValue): ?\App\Models\JournalEntry
    {
        $hasGain = bccomp($gainValue, '0', 2) === 1;
        $hasLoss = bccomp($lossValue, '0', 2) === 1;

        if (! $hasGain && ! $hasLoss) {
            return null;
        }

        $inventoryAccountId = $this->accountRegistry->id(SystemAccount::Inventory);
        $contraAccountId = $this->accountRegistry->id($adjustment->reason->contraAccount());
        $journalLines = [];

        if ($hasGain) {
            $journalLines[] = JournalLineDraft::debit($inventoryAccountId, $gainValue);
            $journalLines[] = JournalLineDraft::credit($contraAccountId, $gainValue);
        }

        if ($hasLoss) {
            $journalLines[] = JournalLineDraft::debit($contraAccountId, $lossValue);
            $journalLines[] = JournalLineDraft::credit($inventoryAccountId, $lossValue);
        }

        return $this->journalService->post(new JournalDraft(
            CarbonImmutable::parse($adjustment->adjustment_date->format('Y-m-d')),
            __('inventory.adjustments.journal_description', [
                'number' => $adjustment->adjustment_number,
                'reason' => $adjustment->reason->label(),
            ]),
            $journalLines,
            $adjustment->branch_id === null ? null : (int) $adjustment->branch_id,
            'stock_adjustment',
            (int) $adjustment->getKey(),
            'approved',
        ));
    }

    /**
     * The cost of added stock: the cost typed on the line, else the warehouse's current average.
     */
    private function gainCost(StockAdjustmentLine $line, int $warehouseId): string
    {
        if ($line->unit_cost !== null) {
            return (string) $line->unit_cost;
        }

        $average = (string) (StockCost::query()
            ->where('warehouse_id', $warehouseId)
            ->where('variant_id', $line->variant_id)
            ->value('average_cost') ?? '0');

        if (bccomp($average, '0', 4) !== 1) {
            throw BusinessRuleException::translated('inventory.errors.cost_required');
        }

        return bcadd($average, '0', 4);
    }

    private function existingBatchId(int $variantId, string $batchNumber): int
    {
        $batchId = StockBatch::query()
            ->where('variant_id', $variantId)
            ->where('batch_number', $batchNumber)
            ->value('id');

        return $batchId === null
            ? throw BusinessRuleException::translated('inventory.errors.batch_missing')
            : (int) $batchId;
    }

    /**
     * Checks every requested line and returns the data to store.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(AdjustmentReason $reason, array $rows): array
    {
        if ($rows === [] || count($rows) > self::MAX_LINES) {
            throw BusinessRuleException::translated('inventory.errors.line_count', ['max' => self::MAX_LINES]);
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', array_map(static fn (array $row): int => (int) ($row['variant_id'] ?? 0), $rows))
            ->get()
            ->keyBy('id');

        $lines = [];
        $seenKeys = [];

        foreach ($rows as $row) {
            $variant = $variants->get((int) ($row['variant_id'] ?? 0))
                ?? throw BusinessRuleException::translated('inventory.errors.variant_missing');

            $quantityText = trim((string) ($row['quantity_change'] ?? ''));

            if (preg_match(self::QUANTITY_PATTERN, $quantityText) !== 1) {
                throw BusinessRuleException::translated('inventory.errors.invalid_quantity', ['quantity' => $quantityText]);
            }

            $quantity = bcadd($quantityText, '0', 4);

            if (bccomp($quantity, '0', 4) === 0) {
                throw BusinessRuleException::translated('inventory.errors.zero_quantity');
            }

            $isGain = bccomp($quantity, '0', 4) === 1;

            if (($isGain && ! $reason->allowsGain()) || (! $isGain && ! $reason->allowsLoss())) {
                throw BusinessRuleException::translated('inventory.errors.reason_direction', ['reason' => $reason->label()]);
            }

            $batchNumber = strtoupper(trim((string) ($row['batch_number'] ?? '')));
            $expiryDate = null;

            if ($variant->product->track_batches) {
                if ($batchNumber === '') {
                    throw BusinessRuleException::translated('inventory.errors.batch_required');
                }

                if (preg_match(self::BATCH_PATTERN, $batchNumber) !== 1) {
                    throw BusinessRuleException::translated('inventory.errors.invalid_batch_number');
                }

                // An expiry date only matters when stock of a (new) batch is added.
                if ($isGain && trim((string) ($row['expiry_date'] ?? '')) !== '') {
                    $expiryDate = $this->parseDate(trim((string) $row['expiry_date']))->format('Y-m-d');
                }
            } else {
                if ($batchNumber !== '') {
                    throw BusinessRuleException::translated('inventory.errors.batch_not_tracked');
                }

                $batchNumber = null;
            }

            $unitCost = null;

            if ($isGain && trim((string) ($row['unit_cost'] ?? '')) !== '') {
                $costText = trim((string) $row['unit_cost']);

                if (preg_match(self::COST_PATTERN, $costText) !== 1) {
                    throw BusinessRuleException::translated('inventory.errors.invalid_cost', ['cost' => $costText]);
                }

                $unitCost = bcadd($costText, '0', 4);
            }

            $key = $variant->getKey() . '|' . ($batchNumber ?? '');

            if (in_array($key, $seenKeys, true)) {
                throw BusinessRuleException::translated('inventory.errors.duplicate_line');
            }

            $seenKeys[] = $key;

            $lines[] = [
                'variant_id' => $variant->getKey(),
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity_change' => $quantity,
                'unit_cost' => $unitCost,
                'notes' => isset($row['notes']) && trim((string) $row['notes']) !== '' ? mb_substr(trim((string) $row['notes']), 0, 255) : null,
            ];
        }

        return $lines;
    }

    private function parseDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw BusinessRuleException::translated('inventory.errors.invalid_date', ['date' => $date]);
        }

        return $parsed;
    }

    /**
     * Numbers are per branch and year: ADJ-<branch code or HO>-<year>-000001.
     */
    private function nextNumber(Warehouse $warehouse, CarbonImmutable $adjustmentDate): string
    {
        $branchCode = $warehouse->branch_id !== null
            ? (string) (Branch::withTrashed()->whereKey($warehouse->branch_id)->value('code') ?? 'BR' . $warehouse->branch_id)
            : 'HO';
        $year = $adjustmentDate->format('Y');

        return $this->documentNumbers->next("stock_adjustment:{$branchCode}:{$year}", "ADJ-{$branchCode}-{$year}-");
    }
}
