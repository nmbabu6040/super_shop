<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\AccountingReportRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\ReportCsvWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportController extends Controller
{
    private const LEDGER_DISPLAY_LIMIT = 2000;

    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly ReportCsvWriter $csvWriter,
    ) {
    }

    public function trialBalance(AccountingReportRequest $request): View
    {
        return view('admin.accounting.reports.trial-balance', [
            'report' => $this->balances->trialBalance($request->asOf(), $request->branchIds()),
            'asOf' => $request->asOf()->format('Y-m-d'),
            'branches' => $request->user()->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'branchFilter' => (int) $request->input('branch_id', 0),
        ]);
    }

    public function trialBalanceExport(AccountingReportRequest $request): StreamedResponse
    {
        Gate::authorize('exportReports', JournalEntry::class);

        $report = $this->balances->trialBalance($request->asOf(), $request->branchIds());

        $rows = array_map(static fn (array $row): array => [
            $row['account']->code,
            (string) $row['account']->name,
            $row['account']->type->value,
            $row['debit'],
            $row['credit'],
        ], $report['rows']);

        $rows[] = ['', 'TOTAL', '', $report['total_debit'], $report['total_credit']];

        return $this->csvWriter->download(
            'trial-balance-' . $request->asOf()->format('Y-m-d') . '.csv',
            ['Code', 'Account', 'Type', 'Debit', 'Credit'],
            $rows,
        );
    }

    public function generalLedger(AccountingReportRequest $request): View
    {
        $account = $request->filled('account_id') ? Account::query()->find((int) $request->input('account_id')) : null;

        return view('admin.accounting.reports.general-ledger', [
            'ledger' => $account === null
                ? null
                : $this->balances->ledger($account, $request->from(), $request->to(), $request->branchIds(), self::LEDGER_DISPLAY_LIMIT),
            'accountOptions' => Account::query()
                ->where('is_postable', true)
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Account $postable): array => [$postable->getKey() => $postable->code . ' · ' . $postable->name])
                ->all(),
            'accountFilter' => $account?->getKey(),
            'from' => $request->from()->format('Y-m-d'),
            'to' => $request->to()->format('Y-m-d'),
            'branches' => $request->user()->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'branchFilter' => (int) $request->input('branch_id', 0),
        ]);
    }

    public function generalLedgerExport(AccountingReportRequest $request): StreamedResponse
    {
        Gate::authorize('exportReports', JournalEntry::class);

        $account = Account::query()->findOrFail((int) $request->input('account_id'));
        $ledger = $this->balances->ledger($account, $request->from(), $request->to(), $request->branchIds());

        $rows = [['', '', 'Opening balance', '', '', '', $ledger['opening']]];

        foreach ($ledger['rows'] as $row) {
            $rows[] = [$row['entry_date'], $row['entry_number'], $row['description'], $row['memo'], $row['debit'], $row['credit'], $row['balance']];
        }

        $rows[] = ['', '', 'Closing balance', '', $ledger['total_debit'], $ledger['total_credit'], $ledger['closing']];

        return $this->csvWriter->download(
            'ledger-' . $account->code . '-' . $request->from()->format('Y-m-d') . '-' . $request->to()->format('Y-m-d') . '.csv',
            ['Date', 'Entry', 'Description', 'Memo', 'Debit', 'Credit', 'Balance'],
            $rows,
        );
    }
}
