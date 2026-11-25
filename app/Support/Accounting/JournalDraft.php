<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use Carbon\CarbonImmutable;

/**
 * Everything needed to post a journal entry. The business modules (POS, purchases,
 * payroll...) build a draft and hand it to App\Services\Accounting\JournalService.
 */
final class JournalDraft
{
    /**
     * @param  list<JournalLineDraft>  $lines
     */
    public function __construct(
        public readonly CarbonImmutable $entryDate,
        public readonly string $description,
        public readonly array $lines,
        public readonly ?int $branchId = null,
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        public readonly ?string $sourceEvent = null,
    ) {}

    public function totalDebit(): string
    {
        $total = '0.00';

        foreach ($this->lines as $line) {
            $total = bcadd($total, $line->debit, 2);
        }

        return $total;
    }

    public function totalCredit(): string
    {
        $total = '0.00';

        foreach ($this->lines as $line) {
            $total = bcadd($total, $line->credit, 2);
        }

        return $total;
    }

    public function hasSource(): bool
    {
        return $this->sourceType !== null && $this->sourceId !== null && $this->sourceEvent !== null;
    }
}
