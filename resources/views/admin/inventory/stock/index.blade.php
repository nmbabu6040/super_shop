<x-layouts.admin :title="__('inventory.stock.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('inventory.stock.title'), null]]">
    <x-admin.page-header :title="__('inventory.stock.title')" :subtitle="__('inventory.stock.subtitle')">
        @can('export', \App\Models\StockLevel::class)
            <a href="{{ route('admin.stock.export', request()->query()) }}" class="btn btn-ghost">
                <x-ui.icon name="download" class="size-4" />
                {{ __('journal.export_csv') }}
            </a>
        @endcan
    </x-admin.page-header>

    @include('admin.inventory.stock._tabs', ['active' => 'stock'])

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
            <label for="status"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.status') }}</label>
            <select id="status" name="status"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach (['low', 'out', 'ok'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($statusFilter === $statusOption)>
                        {{ __('inventory.stock.status.' . $statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.stock.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.product') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.warehouse') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.stock.on_hand') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.stock.reorder_level') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.status') }}</th>
                    @if ($canSeeCosts)
                        <th class="px-4 py-3 text-end">{{ __('inventory.stock.average_cost') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('inventory.fields.value') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $onHand = bcadd((string) $row->on_hand, '0', 4);
                        $status = \App\Services\Inventory\StockReportService::statusOf(
                            $onHand,
                            (string) $row->reorder_level,
                        );
                    @endphp
                    <tr class="border-t border-line">
                        <td class="px-4 py-3">
                            {{ $row->variant->product->name }}@if ($row->variant->options)
                                — {{ implode(' / ', $row->variant->options) }}
                            @endif
                            <div class="font-mono text-xs text-ink-muted">{{ $row->variant->sku }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $row->warehouse->name }}</td>
                        <td class="px-4 py-3 text-end font-medium">
                            <a href="{{ route('admin.stock.movements', ['warehouse_id' => $row->warehouse_id, 'search' => $row->variant->sku]) }}"
                                class="text-brand-700 hover:underline dark:text-brand-300">{{ $onHand }}</a>
                            {{ $row->variant->product->unit?->symbol }}
                        </td>
                        <td class="px-4 py-3 text-end">
                            {{ bccomp((string) $row->reorder_level, '0', 4) === 1 ? bcadd((string) $row->reorder_level, '0', 4) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($status) {
                                'out' => 'danger',
                                'low' => 'warning',
                                default => 'success',
                            }">
                                {{ __('inventory.stock.status.' . $status) }}
                            </x-admin.badge>
                        </td>
                        @if ($canSeeCosts)
                            <td class="px-4 py-3 text-end">{{ bcadd((string) $row->average_cost, '0', 4) }}</td>
                            <td class="px-4 py-3 text-end">
                                {{ \App\Support\Money\MoneyFormatter::format(bcadd(bcmul($onHand, (string) $row->average_cost, 6), '0', 2)) }}
                            </td>
                        @endif
                    </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canSeeCosts ? 7 : 5 }}" class="px-4 py-10 text-center text-ink-muted">
                                {{ __('admin.common.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </x-layouts.admin>
