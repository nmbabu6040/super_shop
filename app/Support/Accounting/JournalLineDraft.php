<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use App\Exceptions\BusinessRuleException;

/**
 * One line of a journal entry that is about to be posted. Amounts are exact decimal strings
 * with at most two decimals; anything else is refused instead of being silently rounded.
 */
final class JournalLineDraft
{
    public readonly string $debit;

    public readonly string $credit;

    public function __construct(
        public readonly int $accountId,
        string $debit = '0',
        string $credit = '0',
        public readonly ?string $memo = null,
        public readonly ?int $branchId = null,
    ) {
        $this->debit = self::normalize($debit);
        $this->credit = self::normalize($credit);
    }

    public static function debit(int $accountId, string $amount, ?string $memo = null, ?int $branchId = null): self
    {
        return new self($accountId, $amount, '0', $memo, $branchId);
    }

    public static function credit(int $accountId, string $amount, ?string $memo = null, ?int $branchId = null): self
    {
        return new self($accountId, '0', $amount, $memo, $branchId);
    }

    private static function normalize(string $amount): string
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1) {
            throw BusinessRuleException::translated('accounting.errors.invalid_amount', ['amount' => $amount]);
        }

        return bcadd($amount, '0', 2);
    }
}
