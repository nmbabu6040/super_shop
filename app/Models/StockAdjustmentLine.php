<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockAdjustmentLine extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'variant_id',
        'batch_number',
        'expiry_date',
        'quantity_change',
        'unit_cost',
        'notes',
        'line_number',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity_change' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'applied_unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // The request of a line never changes; only the result of approving it is recorded.
        static::updating(static function (StockAdjustmentLine $line): void {
            $allowed = ['batch_id', 'stock_movement_id', 'applied_unit_cost', 'total_cost', 'updated_at'];

            if (array_diff(array_keys($line->getDirty()), $allowed) !== []) {
                throw new LogicException('The lines of a stock adjustment cannot be changed.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('The lines of a stock adjustment cannot be deleted.');
        });
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id')->withoutGlobalScopes();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id')->withoutGlobalScopes();
    }
}
