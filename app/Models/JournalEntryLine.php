<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One debit or credit of a journal entry. Lines are immutable.
 */
class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'branch_id',
        'debit',
        'credit',
        'memo',
        'line_number',
    ];

    protected function casts(): array
    {
        return [
            // Exact decimal strings: money never passes through floats.
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'line_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Journal entry lines cannot be changed; reverse the entry instead.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Journal entry lines cannot be deleted; reverse the entry instead.');
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class)->withoutGlobalScopes();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
