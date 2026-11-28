<x-layouts.admin :title="__('inventory.stock.movements_title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('inventory.stock.movements_title'), null]]">
    <x-admin.page-header :title="__('inventory.stock.movements_title')" :subtitle="__('inventory.stock.movements_subtitle')" />

    @include('admin.inventory.stock._tabs', ['active' => 'movements'])

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
            <label for="search"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.search') }}</label>
            <input id="search" name="search" type="search" value="{{ $searchTerm }}"
                placeholder="{{ __('inventory.stock.search_placeholder') }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="warehouse_id"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.warehouse') }}</label>
            <select id="warehouse_id" name="warehouse_id"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($warehouseFilter === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.stock.movement_type') }}</label>
            <select id="type" name="type"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($typeFilter === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $fromDate }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $toDate }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.stock.movements') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.date') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.product') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.warehouse') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.stock.movement_type') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.fields.quantity_change') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.stock.balance_after') }}</th>
                    @if ($canSeeCosts)
                        <th class="px-4 py-3 text-end">{{ __('inventory.fields.value') }}</th>
                    @endif
                    <th class="px-4 py-3 text-start">{{ __('inventory.stock.source') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $movement->occurred_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            {{ $movement->variant->product->name }}
                            <div class="font-mono text-xs text-ink-muted">{{ $movement->variant->sku }}@if ($movement->batch)
                                    · {{ $movement->batch->batch_number }}
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $movement->warehouse->name }}</td>
                        <td class="px-4 py-3">{{ $movement->movement_type->label() }}</td>
                        <td
                            class="px-4 py-3 text-end font-medium {{ bccomp((string) $movement->quantity, '0', 4) === -1 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ $movement->quantity }}</td>
                        <td class="px-4 py-3 text-end">{{ $movement->balance_after }}</td>
                        @if ($canSeeCosts)
                            <td class="px-4 py-3 text-end">
                                {{ \App\Support\Money\MoneyFormatter::format($movement->total_cost) }}</td>
                        @endif
                        <td class="px-4 py-3 text-xs text-ink-muted">
                            @if ($movement->source_type)
                                {{ $movement->source_type }} #{{ $movement->source_id }}
                            @endif
                            @if ($movement->creator)
                                <div>{{ $movement->creator->name }}</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canSeeCosts ? 8 : 7 }}" class="px-4 py-10 text-center text-ink-muted">
                                {{ __('admin.common.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $movements->links() }}</div>
    </x-layouts.admin>
