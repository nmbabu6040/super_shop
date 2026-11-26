<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\AccountRequest;
use App\Models\Account;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\AccountTree;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountTree $accountTree,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Account::class);

        $searchTerm = trim((string) $request->query('search', ''));
        $typeFilter = AccountType::tryFrom((string) $request->query('type', ''));
        $activeFilter = (string) $request->query('active', '');

        $isFiltered = $searchTerm !== '' || $typeFilter !== null || in_array($activeFilter, ['0', '1'], true);

        $accounts = Account::query()
            ->with('parent')
            ->withCount('children')
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('code', 'like', "%{$searchTerm}%")
                        ->orWhere('name->en', 'like', "%{$searchTerm}%")
                        ->orWhere('name->bn', 'like', "%{$searchTerm}%");
                });
            })
            ->when($typeFilter !== null, fn (Builder $query) => $query->where('type', $typeFilter->value))
            ->when(in_array($activeFilter, ['0', '1'], true), fn (Builder $query) => $query->where('is_active', $activeFilter === '1'))
            ->orderBy('code')
            ->get();

        $rows = $isFiltered
            ? $accounts->map(static fn (Account $account): array => ['account' => $account, 'depth' => 0])->all()
            : $this->accountTree->flatten($accounts);

        return view('admin.accounting.accounts.index', [
            'rows' => $rows,
            'types' => AccountType::cases(),
            'searchTerm' => $searchTerm,
            'typeFilter' => $typeFilter?->value,
            'activeFilter' => $activeFilter,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Account::class);

        return view('admin.accounting.accounts.form', $this->formData(null));
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $this->accountService->create($request->validated());

        return redirect()->route('admin.accounts.index')->with('success', __('accounting.accounts.created'));
    }

    public function edit(Account $account): View
    {
        Gate::authorize('update', $account);

        return view('admin.accounting.accounts.form', $this->formData($account));
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $this->accountService->update($account, $request->validated());

        return redirect()->route('admin.accounts.index')->with('success', __('accounting.accounts.updated'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $this->accountService->delete($account);

        return redirect()->route('admin.accounts.index')->with('success', __('accounting.accounts.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Account $account): array
    {
        $excludedIds = $account !== null
            ? [$account->getKey(), ...$this->accountService->descendantIds($account)]
            : [];

        $parentOptions = [];

        // Only group accounts can have sub-accounts.
        foreach ($this->accountTree->flatten(Account::query()->where('is_postable', false)->get()) as $row) {
            if (in_array($row['account']->getKey(), $excludedIds, true)) {
                continue;
            }

            $parentOptions[$row['account']->getKey()] = str_repeat('— ', $row['depth']) . $row['account']->code . ' · ' . $row['account']->name;
        }

        return [
            'account' => $account?->loadCount(['lines', 'children']),
            'parentOptions' => $parentOptions,
            'typeOptions' => collect(AccountType::cases())->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])->all(),
        ];
    }
}
