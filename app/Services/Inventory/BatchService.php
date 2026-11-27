<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Finds or creates the batch (lot) of a variant. A batch number is unique per variant and the
 * expiry date of an existing batch is never silently changed.
 */
final class BatchService
{
    public function findOrCreate(int $variantId, string $batchNumber, ?string $expiryDate = null, ?string $manufacturedOn = null): StockBatch
    {
        $number = strtoupper(trim($batchNumber));

        if (preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,59}$/', $number) !== 1) {
            throw BusinessRuleException::translated('inventory.errors.invalid_batch_number');
        }

        $this->assertDate($expiryDate);
        $this->assertDate($manufacturedOn);

        if (! ProductVariant::query()->whereKey($variantId)->exists()) {
            throw BusinessRuleException::translated('inventory.errors.variant_missing');
        }

        $find = static fn (): ?StockBatch => StockBatch::query()
            ->where('variant_id', $variantId)
            ->where('batch_number', $number)
            ->first();

        $batch = $find();

        if ($batch === null) {
            try {
                return DB::transaction(static fn (): StockBatch => StockBatch::query()->create([
                    'variant_id' => $variantId,
                    'batch_number' => $number,
                    'expiry_date' => $expiryDate,
                    'manufactured_on' => $manufacturedOn,
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                // Created by a concurrent request a moment ago.
                $batch = $find() ?? throw $exception;
            }
        }

        if ($expiryDate !== null && $batch->expiry_date?->format('Y-m-d') !== $expiryDate) {
            throw BusinessRuleException::translated('inventory.errors.batch_expiry_conflict', [
                'batch' => $batch->batch_number,
                'expiry' => $batch->expiry_date?->format('Y-m-d') ?? '-',
            ]);
        }

        return $batch;
    }

    private function assertDate(?string $date): void
    {
        if ($date === null) {
            return;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || date('Y-m-d', (int) strtotime($date)) !== $date) {
            throw BusinessRuleException::translated('inventory.errors.invalid_date', ['date' => $date]);
        }
    }
}
