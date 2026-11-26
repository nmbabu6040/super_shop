<x-layouts.admin :title="__('journal.trial.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('journal.trial.title'), null]]">
    <x-admin.page-header :title="__('journal.trial.title')" :subtitle="__('journal.trial.subtitle')">
        @can('exportReports', \App\Models\JournalEntry::class)
            <a href="{{ route('admin.reports.trial-balance.export', request()->query()) }}" class="btn btn-ghost">
                <x-ui.icon name="download" class="size-4" />
                {{ __('journal.export_csv') }}
            </a>
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
            <label for="as_of"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.trial.as_of') }}</label>
            <input id="as_of" name="as_of" type="date" value="{{ $asOf }}"
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

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('accounting.fields.code') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.account') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('accounting.fields.type') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('journal.fields.debit') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('journal.fields.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['account']->code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.reports.general-ledger', ['account_id' => $row['account']->id, 'to' => $asOf, 'from' => \Carbon\CarbonImmutable::parse($asOf)->startOfYear()->format('Y-m-d'), 'branch_id' => $branchFilter ?: null]) }}"
                                class="text-brand-700 hover:underline dark:text-brand-300">{{ $row['account']->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $row['account']->type->label() }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ bccomp($row['debit'], '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($row['debit']) : '' }}
                        </td>
                        <td class="px-4 py-3 text-end">
                            {{ bccomp($row['credit'], '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($row['credit']) : '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t border-line font-semibold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-end">{{ __('journal.totals') }}</td>
                    <td class="px-4 py-3 text-end">
                        {{ \App\Support\Money\MoneyFormatter::format($report['total_debit']) }}</td>
                    <td class="px-4 py-3 text-end">
                        {{ \App\Support\Money\MoneyFormatter::format($report['total_credit']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p
        class="mt-3 text-sm {{ bccomp($report['total_debit'], $report['total_credit'], 2) === 0 ? 'text-emerald-600' : 'text-red-600' }}">
        {{ bccomp($report['total_debit'], $report['total_credit'], 2) === 0 ? __('journal.trial.balanced') : __('journal.trial.not_balanced') }}
    </p>
</x-layouts.admin>
