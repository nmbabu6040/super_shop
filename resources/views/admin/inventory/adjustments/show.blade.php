@php
    $statusTone = match ($adjustment->status) {
        \App\Enums\JournalEntryStatus::Posted => 'success',
        \App\Enums\JournalEntryStatus::Pending => 'warning',
        \App\Enums\JournalEntryStatus::Rejected => 'danger',
    };
    $fieldClasses =
        'w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20';
@endphp
<x-layouts.admin :title="$adjustment->adjustment_number" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('inventory.adjustments.title'), route('admin.stock-adjustments.index')],
    [$adjustment->adjustment_number, null],
]">
    <x-admin.page-header :title="$adjustment->adjustment_number" :subtitle="$adjustment->reason->label()">
        <x-admin.badge :tone="$statusTone">{{ $adjustment->status->label() }}</x-admin.badge>
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

    <section class="mb-6 rounded-card border border-line bg-surface-raised p-6 shadow-card">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-ink-muted">{{ __('inventory.fields.date') }}</dt>
                <dd class="font-medium">{{ $adjustment->adjustment_date->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('inventory.fields.warehouse') }}</dt>
                <dd class="font-medium">{{ $adjustment->warehouse->name }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('inventory.fields.creator') }}</dt>
                <dd class="font-medium">{{ $adjustment->creator?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">{{ __('inventory.fields.reviewer') }}</dt>
                <dd class="font-medium">
                    {{ $adjustment->reviewer ? $adjustment->reviewer->name . ' · ' . $adjustment->reviewed_at?->translatedFormat('d M Y H:i') : '—' }}
                </dd>
            </div>
            @if ($adjustment->total_value !== null)
                <div>
                    <dt class="text-ink-muted">{{ __('inventory.fields.total_value') }}</dt>
                    <dd class="font-medium">{{ \App\Support\Money\MoneyFormatter::format($adjustment->total_value) }}
                    </dd>
                </div>
            @endif
            @if ($adjustment->journalEntry)
                <div>
                    <dt class="text-ink-muted">{{ __('inventory.fields.journal') }}</dt>
                    <dd><a class="font-medium text-brand-700 hover:underline dark:text-brand-300"
                            href="{{ route('admin.journal-entries.show', $adjustment->journalEntry) }}">{{ $adjustment->journalEntry->entry_number }}</a>
                    </dd>
                </div>
            @endif
            @if ($adjustment->notes)
                <div class="sm:col-span-2">
                    <dt class="text-ink-muted">{{ __('inventory.fields.notes') }}</dt>
                    <dd class="font-medium">{{ $adjustment->notes }}</dd>
                </div>
            @endif
            @if ($adjustment->rejection_reason)
                <div class="sm:col-span-2">
                    <dt class="text-ink-muted">{{ __('journal.rejection_reason') }}</dt>
                    <dd class="font-medium">{{ $adjustment->rejection_reason }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <div class="mb-6 overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">#</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.product') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.batch') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.fields.quantity_change') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.fields.applied_cost') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.fields.value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($adjustment->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3">{{ $line->line_number }}</td>
                        <td class="px-4 py-3">
                            {{ $line->variant->product->name }}@if ($line->variant->options)
                                — {{ implode(' / ', $line->variant->options) }}
                            @endif
                            <div class="font-mono text-xs text-ink-muted">{{ $line->variant->sku }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $line->batch_number ?? '—' }}
                            @if ($line->expiry_date)
                                <div class="text-xs text-ink-muted">{{ $line->expiry_date->translatedFormat('d M Y') }}
                                </div>
                            @endif
                        </td>
                        <td
                            class="px-4 py-3 text-end {{ bccomp((string) $line->quantity_change, '0', 4) === -1 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ $line->quantity_change }}</td>
                        <td class="px-4 py-3 text-end">{{ $line->applied_unit_cost ?? ($line->unit_cost ?? '—') }}</td>
                        <td class="px-4 py-3 text-end">
                            {{ $line->total_cost !== null ? \App\Support\Money\MoneyFormatter::format($line->total_cost) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($adjustment->status === \App\Enums\JournalEntryStatus::Pending)
        <div class="flex flex-wrap items-start gap-4">
            @can('approve', $adjustment)
                <form method="POST" action="{{ route('admin.stock-adjustments.approve', $adjustment) }}"
                    data-confirm="{{ __('inventory.adjustments.confirm_approve') }}"
                    data-confirm-title="{{ __('admin.common.confirm_title') }}"
                    data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                    data-confirm-no="{{ __('admin.common.confirm_no') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('journal.actions.approve') }}</button>
                </form>
            @endcan
            @can('reject', $adjustment)
                <details class="rounded-xl border border-line bg-surface-raised p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-red-600">{{ __('journal.actions.reject') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.stock-adjustments.reject', $adjustment) }}"
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
        </div>
    @endif
</x-layouts.admin>
