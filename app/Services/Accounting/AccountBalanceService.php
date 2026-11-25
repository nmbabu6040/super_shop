<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Carbon\CarbonInterface;
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
        return DB::table('journal_entry_lines as line')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.status', JournalEntryStatus::Posted->value)
            ->whereDate('entry.entry_date', '<=', $asOf->format('Y-m-d'))
            ->when($from !== null, fn($query) => $query->whereDate('entry.entry_date', '>=', $from->format('Y-m-d')))
            ->when($branchIds !== null, fn($query) => $query->whereIn('line.branch_id', $branchIds))
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
}
