<x-layouts.admin :title="__('inventory.adjustments.title')"
                 :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('inventory.adjustments.title'), null]]">
    <x-admin.page-header :title="__('inventory.adjustments.title')" :subtitle="__('inventory.adjustments.subtitle')">
        @can('create', \App\Models\StockAdjustment::class)
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('inventory.adjustments.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.search') }}</label>
            <input id="search" name="search" type="search" value="{{ $searchTerm }}" placeholder="{{ __('inventory.adjustments.search_placeholder') }}"
                   class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.status') }}</label>
            <select id="status" name="status" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="reason" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.reason') }}</label>
            <select id="reason" name="reason" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}" @selected($reasonFilter === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="warehouse_id" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.warehouse') }}</label>
            <select id="warehouse_id" name="warehouse_id" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="0">{{ __('admin.common.all') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($warehouseFilter === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $fromDate }}" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $toDate }}" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.number') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.date') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.warehouse') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.reason') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.creator') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adjustments as $adjustment)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('admin.stock-adjustments.show', $adjustment) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ $adjustment->adjustment_number }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $adjustment->adjustment_date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $adjustment->warehouse->name }}</td>
                        <td class="px-4 py-3">{{ $adjustment->reason->label() }}</td>
                        <td class="px-4 py-3">{{ $adjustment->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($adjustment->status) {
                                \App\Enums\JournalEntryStatus::Posted => 'success',
                                \App\Enums\JournalEntryStatus::Pending => 'warning',
                                \App\Enums\JournalEntryStatus::Rejected => 'danger',
                            }">{{ $adjustment->status->label() }}</x-admin.badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-ink-muted">{{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $adjustments->links() }}</div>
</x-layouts.admin>
