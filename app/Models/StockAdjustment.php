<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\JournalEntryStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A stock adjustment document. It is pending until a second person approves it; approving
 * applies the stock changes and posts the accounting entry. A posted or rejected adjustment
 * is never changed or deleted.
 */
class StockAdjustment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'branch_id',
        'adjustment_date',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'reason' => AdjustmentReason::class,
            'status' => JournalEntryStatus::class,
            'total_value' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (StockAdjustment $adjustment): void {
            if ($adjustment->getRawOriginal('status') === JournalEntryStatus::Pending->value) {
                return;
            }

            if (array_diff(array_keys($adjustment->getDirty()), ['updated_at']) !== []) {
                throw new LogicException('A posted or rejected stock adjustment cannot be changed.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Stock adjustments are never deleted.');
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

    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class)->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class)->withoutGlobalScopes();
    }
}
