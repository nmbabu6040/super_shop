<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free document numbers. The counter row is locked while the number is taken, so two
 * requests can never receive the same number; and because the increment is part of the
 * caller's transaction, a rolled-back document gives its number back (no gaps).
 */
final class DocumentNumberService
{
    public function next(string $sequenceKey, string $prefix, int $padding = 6): string
    {
        return DB::transaction(function () use ($sequenceKey, $prefix, $padding): string {
            DocumentSequence::query()->insertOrIgnore([[
                'key' => $sequenceKey,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            $sequence = DocumentSequence::query()->where('key', $sequenceKey)->lockForUpdate()->firstOrFail();
            $sequence->last_number++;
            $sequence->save();

            return $prefix . str_pad((string) $sequence->last_number, $padding, '0', STR_PAD_LEFT);
        });
    }
}
