<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A journal entry. Once it is posted (or rejected) it can never be changed or deleted:
 * only the "reversed" marker may be set. Corrections are made with a reversing entry.
 * BelongsToBranch limits branch-bound staff to the entries of their own branches
 * (head office entries without a branch are visible to unrestricted users only).
 */
class JournalEntry extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'accounting_period_id',
        'branch_id',
        'description',
        'total_amount',
        'source_type',
        'source_id',
        'source_event',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalEntryStatus::class,
            'total_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (JournalEntry $entry): void {
            // A pending entry is moved to posted/rejected by the journal service.
            if ($entry->getRawOriginal('status') === JournalEntryStatus::Pending->value) {
                return;
            }

            if (array_diff(array_keys($entry->getDirty()), ['reversed_at', 'updated_at']) !== []) {
                throw new LogicException('A posted or rejected journal entry cannot be changed; reverse it instead.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Journal entries are never deleted; reverse them instead.');
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversalEntry(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
