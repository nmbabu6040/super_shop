<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Balances computed from POSTED journal lines only (pending and rejected entries count for nothing).
 */
final class AccountBalanceService
{
    /**
     * Total debits and credits per account up to and including a date.
     *
     * @param  list<int>|null  $branchIds  null = every branch (including head office lines);
     *                                      a list = only lines of these branches (an empty list matches nothing)
     * @return Collection<int, object{account_id: int, total_debit: string, total_credit: string}>
     */
    public function totalsByAccount(CarbonInterface $asOf, ?array $branchIds = null, ?CarbonInterface $from = null): Collection
    {
        return $this->postedLines($branchIds)
            ->whereDate('entry.entry_date', '<=', $asOf->format('Y-m-d'))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('entry.entry_date', '>=', $from->format('Y-m-d')))
            ->groupBy('line.account_id')
            ->selectRaw('line.account_id as account_id, SUM(line.debit) as total_debit, SUM(line.credit) as total_credit')
            ->get();
    }

    /**
     * The trial balance: every account with activity, its net balance on the debit or the credit
     * side, and the two column totals (which are equal when the books are in balance).
     *
     * @param  list<int>|null  $branchIds
     * @return array{rows: list<array{account: Account, debit: string, credit: string}>, total_debit: string, total_credit: string}
     */
    public function trialBalance(CarbonInterface $asOf, ?array $branchIds = null): array
    {
        $totals = $this->totalsByAccount($asOf, $branchIds)->keyBy('account_id');
        $accounts = Account::query()->whereIn('id', $totals->keys())->orderBy('code')->get();

        $rows = [];
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($accounts as $account) {
            $accountTotals = $totals->get($account->getKey());
            $net = bcsub((string) $accountTotals->total_debit, (string) $accountTotals->total_credit, 2);

            $debit = bccomp($net, '0', 2) === 1 ? $net : '0.00';
            $credit = bccomp($net, '0', 2) === -1 ? bcsub('0', $net, 2) : '0.00';

            $rows[] = ['account' => $account, 'debit' => $debit, 'credit' => $credit];
            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);
        }

        return ['rows' => $rows, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit];
    }

    /**
     * The general ledger of one account: opening balance, every posted line of the period with
     * a running balance, the period totals and the closing balance. Balances are shown on the
     * normal side of the account (debit accounts grow with debits, credit accounts with credits).
     *
     * @param  list<int>|null  $branchIds
     * @param  int|null  $rowLimit  Cap on the listed lines (null = all). Totals and the closing
     *                              balance always cover every line of the period.
     * @return array{
     *     account: Account,
     *     opening: string,
     *     rows: list<array{entry_id: int, entry_number: string, entry_date: string, description: string, memo: ?string, debit: string, credit: string, balance: string}>,
     *     total_debit: string,
     *     total_credit: string,
     *     closing: string,
     *     truncated: bool
     * }
     */
    public function ledger(Account $account, CarbonInterface $from, CarbonInterface $to, ?array $branchIds = null, ?int $rowLimit = null): array
    {
        $isDebitNormal = $account->type->normalBalance() === 'debit';
        $fromText = $from->format('Y-m-d');
        $toText = $to->format('Y-m-d');

        $opening = $this->postedLines($branchIds)
            ->where('line.account_id', $account->getKey())
            ->whereDate('entry.entry_date', '<', $fromText)
            ->selectRaw('COALESCE(SUM(line.debit), 0) as total_debit, COALESCE(SUM(line.credit), 0) as total_credit')
            ->first();

        $periodTotals = $this->postedLines($branchIds)
            ->where('line.account_id', $account->getKey())
            ->whereDate('entry.entry_date', '>=', $fromText)
            ->whereDate('entry.entry_date', '<=', $toText)
            ->selectRaw('COALESCE(SUM(line.debit), 0) as total_debit, COALESCE(SUM(line.credit), 0) as total_credit')
            ->first();

        $openingBalance = $this->signedBalance((string) $opening->total_debit, (string) $opening->total_credit, $isDebitNormal);

        $lines = $this->postedLines($branchIds)
            ->where('line.account_id', $account->getKey())
            ->whereDate('entry.entry_date', '>=', $fromText)
            ->whereDate('entry.entry_date', '<=', $toText)
            ->orderBy('entry.entry_date')
            ->orderBy('entry.id')
            ->orderBy('line.line_number')
            ->select('entry.id as entry_id', 'entry.entry_number', 'entry.entry_date', 'entry.description', 'line.memo', 'line.debit', 'line.credit')
            ->when($rowLimit !== null, fn (Builder $query) => $query->limit($rowLimit + 1))
            ->get();

        $truncated = $rowLimit !== null && $lines->count() > $rowLimit;

        $rows = [];
        $runningBalance = $openingBalance;

        foreach ($lines->take($rowLimit ?? $lines->count()) as $line) {
            $runningBalance = bcadd(
                $runningBalance,
                $this->signedBalance((string) $line->debit, (string) $line->credit, $isDebitNormal),
                2
            );

            $rows[] = [
                'entry_id' => (int) $line->entry_id,
                'entry_number' => (string) $line->entry_number,
                'entry_date' => (string) $line->entry_date,
                'description' => (string) $line->description,
                'memo' => $line->memo,
                'debit' => bcadd((string) $line->debit, '0', 2),
                'credit' => bcadd((string) $line->credit, '0', 2),
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => $account,
            'opening' => $openingBalance,
            'rows' => $rows,
            'total_debit' => bcadd((string) $periodTotals->total_debit, '0', 2),
            'total_credit' => bcadd((string) $periodTotals->total_credit, '0', 2),
            'closing' => bcadd(
                $openingBalance,
                $this->signedBalance((string) $periodTotals->total_debit, (string) $periodTotals->total_credit, $isDebitNormal),
                2
            ),
            'truncated' => $truncated,
        ];
    }

    /**
     * Posted journal lines, optionally limited to some branches.
     *
     * @param  list<int>|null  $branchIds
     */
    private function postedLines(?array $branchIds): Builder
    {
        return DB::table('journal_entry_lines as line')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.status', JournalEntryStatus::Posted->value)
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('line.branch_id', $branchIds));
    }

    private function signedBalance(string $debit, string $credit, bool $isDebitNormal): string
    {
        return $isDebitNormal ? bcsub($debit, $credit, 2) : bcsub($credit, $debit, 2);
    }
}
