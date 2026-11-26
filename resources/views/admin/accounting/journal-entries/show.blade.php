@php
    $statusTone = match ($entry->status) {
        \App\Enums\JournalEntryStatus::Posted => 'success',
        \App\Enums\JournalEntryStatus::Pending => 'warning',
        \App\Enums\JournalEntryStatus::Rejected => 'danger',
    };
    $fieldClasses =
        'w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20';
@endphp
<x-layouts.admin :title="$entry->entry_number" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('journal.title'), route('admin.journal-entries.index')],
    [$entry->entry_number, null],
]">
    <x-admin.page-header :title="$entry->entry_number" :subtitle="$entry->description">
        <x-admin.badge :tone="$statusTone">{{ $entry->status->label() }}</x-admin.badge>
        @if ($entry->isReversed())
            <x-admin.badge tone="neutral">{{ __('journal.reversed_badge') }}</x-admin.badge>
        @endif
    </x-admin.page-header>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100"
            role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $errorMessage)
                    <li>{{ $errorMessage }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-6 rounded-card border border-line bg-surface-raised p-6 shadow-card">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.date') }}</dt>
                <dd class="font-medium">{{ $entry->entry_date->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.period') }}</dt>
                <dd class="font-medium">{{ $entry->accountingPeriod->name }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.branch') }}</dt>
                <dd class="font-medium">{{ $entry->branch?->name ?? __('journal.head_office') }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.total') }}</dt>
                <dd class="font-medium">{{ \App\Support\Money\MoneyFormatter::format($entry->total_amount) }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.creator') }}</dt>
                <dd class="font-medium">{{ $entry->creator?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('journal.fields.reviewer') }}</dt>
                <dd class="font-medium">
                    @if ($entry->reviewer)
                        {{ $entry->reviewer->name }} · {{ $entry->reviewed_at?->translatedFormat('d M Y H:i') }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($entry->source_type)
                <div>
                    <dt class="text-ink-muted">{{ __('journal.fields.source') }}</dt>
                    <dd class="font-medium">{{ $entry->source_type }} #{{ $entry->source_id }}
                        ({{ $entry->source_event }})</dd>
                </div>
            @endif
            @if ($entry->rejection_reason)
                <div class="sm:col-span-2">
                    <dt class="text-ink-muted">{{ __('journal.rejection_reason') }}</dt>
                    <dd class="font-medium">{{ $entry->rejection_reason }}</dd>
                </div>
            @endif
            @if ($entry->reversesEntry)
                <div>
                    <dt class="text-ink-muted">{{ __('journal.reversal_of') }}</dt>
                    <dd><a class="font-medium text-brand-700 hover:underline dark:text-brand-300"
                            href="{{ route('admin.journal-entries.show', $entry->reversesEntry) }}">{{ $entry->reversesEntry->entry_number }}</a>
                    </dd>
                </div>
            @endif
            @if ($entry->reversalEntry)
                <div>
                    <dt class="text-ink-muted">{{ __('journal.reversed_by') }}</dt>
                    <dd><a class="font-medium text-brand-700 hover:underline dark:text-brand-300"
                            href="{{ route('admin.journal-entries.show', $entry->reversalEntry) }}">{{ $entry->reversalEntry->entry_number }}</a>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    <div class="mb-6 overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">#</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.account') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.memo') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.branch') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('journal.fields.debit') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('journal.fields.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entry->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3">{{ $line->line_number }}</td>
                        <td class="px-4 py-3"><span class="font-mono text-xs">{{ $line->account->code }}</span>
                            {{ $line->account->name }}</td>
                        <td class="px-4 py-3">{{ $line->memo }}</td>
                        <td class="px-4 py-3">{{ $line->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ bccomp($line->debit, '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($line->debit) : '' }}
                        </td>
                        <td class="px-4 py-3 text-end">
                            {{ bccomp($line->credit, '0', 2) === 1 ? \App\Support\Money\MoneyFormatter::format($line->credit) : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-line font-semibold">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-end">{{ __('journal.totals') }}</td>
                    <td class="px-4 py-3 text-end">
                        {{ \App\Support\Money\MoneyFormatter::format($entry->total_amount) }}</td>
                    <td class="px-4 py-3 text-end">
                        {{ \App\Support\Money\MoneyFormatter::format($entry->total_amount) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex flex-wrap items-start gap-4">
        @if ($entry->status === \App\Enums\JournalEntryStatus::Pending)
            @can('approve', $entry)
                <form method="POST" action="{{ route('admin.journal-entries.approve', $entry) }}"
                    data-confirm="{{ __('journal.confirm_approve') }}"
                    data-confirm-title="{{ __('admin.common.confirm_title') }}"
                    data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                    data-confirm-no="{{ __('admin.common.confirm_no') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('journal.actions.approve') }}</button>
                </form>
            @endcan
            @can('reject', $entry)
                <details class="rounded-xl border border-line bg-surface-raised p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-red-600">{{ __('journal.actions.reject') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.journal-entries.reject', $entry) }}"
                        class="mt-3 space-y-2">
                        @csrf
                        <label for="reject-reason"
                            class="block text-xs font-medium">{{ __('journal.fields.reason') }}</label>
                        <input id="reject-reason" name="reason" type="text" maxlength="200" required
                            class="{{ $fieldClasses }}">
                        <button type="submit"
                            class="btn btn-ghost text-red-600">{{ __('journal.actions.reject') }}</button>
                    </form>
                </details>
            @endcan
        @endif

        @if (
            $entry->status === \App\Enums\JournalEntryStatus::Posted &&
                !$entry->isReversed() &&
                $entry->reverses_entry_id === null)
            @can('reverse', $entry)
                <details class="rounded-xl border border-line bg-surface-raised p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-amber-700">
                        {{ __('journal.actions.reverse') }}</summary>
                    <form method="POST" action="{{ route('admin.journal-entries.reverse', $entry) }}"
                        class="mt-3 space-y-2">
                        @csrf
                        <label for="reverse-reason"
                            class="block text-xs font-medium">{{ __('journal.fields.reason') }}</label>
                        <input id="reverse-reason" name="reason" type="text" maxlength="200" required
                            class="{{ $fieldClasses }}">
                        <label for="reversal-date"
                            class="block text-xs font-medium">{{ __('journal.fields.reversal_date') }}</label>
                        <input id="reversal-date" name="reversal_date" type="date" class="{{ $fieldClasses }}">
                        <p class="text-xs text-ink-muted">{{ __('journal.reverse_help') }}</p>
                        <button type="submit"
                            class="btn btn-ghost text-amber-700">{{ __('journal.actions.reverse') }}</button>
                    </form>
                </details>
            @endcan
        @endif
    </div>
</x-layouts.admin>
