<x-layouts.admin :title="__('journal.ledger.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('journal.ledger.title'), null]]">
    <x-admin.page-header :title="__('journal.ledger.title')">
        @can('exportReports', \App\Models\JournalEntry::class)
            @if ($ledger)
                <a href="{{ route('admin.reports.general-ledger.export', request()->query()) }}" class="btn btn-ghost">
                    <x-ui.icon name="download" class="size-4" />
                    {{ __('journal.export_csv') }}
                </a>
            @endif
        @endcan
    </x-admin.page-header>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $errorMessage)
                    <li>{{ $errorMessage }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="account_id"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.fields.account') }}</label>
            <select id="account_id" name="account_id"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('journal.ledger.select_account') }}</option>
                @foreach ($accountOptions as $accountId => $accountLabel)
                    <option value="{{ $accountId }}" @selected($accountFilter === $accountId)>{{ $accountLabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $from }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $to }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="branch_id"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.fields.branch') }}</label>
            <select id="branch_id" name="branch_id"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="0">{{ __('admin.common.all') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchFilter === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('admin.common.filter') }}</button>
    </form>

    @if ($ledger)
        <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
            <table class="min-w-full text-sm">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('journal.fields.date') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('journal.fields.number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('journal.fields.description') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('journal.fields.debit') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('journal.fields.credit') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('journal.ledger.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-line bg-surface/60 font-medium">
                        <td colspan="5" class="px-4 py-3">{{ __('journal.ledger.opening') }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ \App\Support\Money\MoneyFormatter::format($ledger['opening']) }}</td>
                    </tr>
                    @foreach ($ledger['rows'] as $row)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3">
                                {{ \Carbon\CarbonImmutable::parse($row['entry_date'])->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ route('admin.journal-entries.show', $row['entry_id']) }}"
                                    class="text-brand-700 hover:underline dark:text-brand-300">{{ $row['entry_number'] }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $row['description'] }}@if ($row['memo'])
                                    <span class="text-ink-muted"> · {{ $row['memo'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                {{ bccomp($row['debit'], '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($row['debit']) : '' }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                {{ bccomp($row['credit'], '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($row['credit']) : '' }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                {{ \App\Support\Money\MoneyFormatter::format($row['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-line font-semibold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-end">{{ __('journal.ledger.closing') }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ \App\Support\Money\MoneyFormatter::format($ledger['total_debit']) }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ \App\Support\Money\MoneyFormatter::format($ledger['total_credit']) }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ \App\Support\Money\MoneyFormatter::format($ledger['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($ledger['truncated'])
            <p class="mt-3 text-sm text-amber-700">{{ __('journal.ledger.truncated') }}</p>
        @endif
    @endif
</x-layouts.admin>
