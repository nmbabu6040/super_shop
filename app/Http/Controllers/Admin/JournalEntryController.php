<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\JournalActionRequest;
use App\Http\Requests\Admin\Accounting\JournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalService;
use App\Support\Branch\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly BranchContext $branchContext,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $searchTerm = trim((string) $request->query('search', ''));
        $statusFilter = JournalEntryStatus::tryFrom((string) $request->query('status', ''));
        $fromDate = $this->validDate($request->query('from'));
        $toDate = $this->validDate($request->query('to'));
        $branchFilter = (int) $request->query('branch_id', 0);

        $entries = JournalEntry::query()
            ->with(['branch', 'creator'])
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('entry_number', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            })
            ->when($statusFilter !== null, fn (Builder $query) => $query->where('status', $statusFilter->value))
            ->when($fromDate !== null, fn (Builder $query) => $query->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate !== null, fn (Builder $query) => $query->whereDate('entry_date', '<=', $toDate))
            ->when($branchFilter > 0, fn (Builder $query) => $query->where('branch_id', $branchFilter))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.accounting.journal-entries.index', [
            'entries' => $entries,
            'statuses' => JournalEntryStatus::cases(),
            'branches' => $request->user()->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'searchTerm' => $searchTerm,
            'statusFilter' => $statusFilter?->value,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'branchFilter' => $branchFilter,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', JournalEntry::class);

        $accountOptions = Account::query()
            ->active()
            ->where('is_postable', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->getKey() => $account->code . ' · ' . $account->name])
            ->all();

        return view('admin.accounting.journal-entries.form', [
            'accountOptions' => $accountOptions,
            'branches' => $request->user()->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'canUseHeadOffice' => $this->branchContext->restrictedBranchIds() === null,
            'defaultBranchId' => $this->branchContext->activeBranchId(),
            'defaultDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(JournalEntryRequest $request): RedirectResponse
    {
        $entry = $this->journalService->createPending($request->toDraft(), $request->user());

        return redirect()
            ->route('admin.journal-entries.show', $entry)
            ->with('success', __('journal.messages.created'));
    }

    public function show(JournalEntry $journalEntry): View
    {
        Gate::authorize('view', $journalEntry);

        $journalEntry->load([
            'lines.account',
            'lines.branch',
            'branch',
            'creator',
            'reviewer',
            'accountingPeriod',
            'reversesEntry',
            'reversalEntry',
        ]);

        return view('admin.accounting.journal-entries.show', ['entry' => $journalEntry]);
    }

    public function approve(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        Gate::authorize('approve', $journalEntry);

        $this->journalService->approve($journalEntry, $request->user());

        return redirect()->route('admin.journal-entries.show', $journalEntry)->with('success', __('journal.messages.approved'));
    }

    public function reject(JournalActionRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        Gate::authorize('reject', $journalEntry);

        $this->journalService->reject($journalEntry, $request->user(), $request->validated('reason'));

        return redirect()->route('admin.journal-entries.show', $journalEntry)->with('success', __('journal.messages.rejected'));
    }

    public function reverse(JournalActionRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        Gate::authorize('reverse', $journalEntry);

        $reversal = $this->journalService->reverse(
            $journalEntry,
            $request->user(),
            $request->validated('reason'),
            $request->filled('reversal_date') ? CarbonImmutable::parse((string) $request->validated('reversal_date')) : null,
        );

        return redirect()->route('admin.journal-entries.show', $reversal)->with('success', __('journal.messages.reversed'));
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
