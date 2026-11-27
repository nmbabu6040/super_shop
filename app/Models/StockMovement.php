<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row of the stock ledger. Rows are never changed or deleted: a mistake is corrected
 * by a new movement in the opposite direction.
 */
class StockMovement extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = [
        'warehouse_id',
        'branch_id',
        'variant_id',
        'batch_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'source_type',
        'source_id',
        'note',
        'created_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'balance_after' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Stock movements cannot be changed; post a new movement instead.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Stock movements cannot be deleted; post a new movement instead.');
        });
    }

    public static function allowsSharedBranchRecords(): bool
    {
        return true;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
