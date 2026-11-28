@php($today = \Carbon\CarbonImmutable::today())
<x-layouts.admin :title="__('inventory.stock.batches_title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('inventory.stock.batches_title'), null]]">
    <x-admin.page-header :title="__('inventory.stock.batches_title')" :subtitle="__('inventory.stock.batches_subtitle', ['days' => $alertDays])" />

    @include('admin.inventory.stock._tabs', ['active' => 'batches'])

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
            <label for="expiry"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('inventory.fields.expiry') }}</label>
            <select id="expiry" name="expiry"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach (['expired', 'soon', 'ok'] as $expiryOption)
                    <option value="{{ $expiryOption }}" @selected($expiryFilter === $expiryOption)>
                        {{ __('inventory.stock.expiry.' . $expiryOption) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.stock.batches') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.product') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.warehouse') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.batch') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.expiry') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('inventory.stock.on_hand') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('inventory.fields.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($levels as $level)
                    @php
                        $expiryDate = $level->batch->expiry_date;
                        $state =
                            $expiryDate === null
                                ? 'ok'
                                : ($expiryDate->startOfDay()->lt($today)
                                    ? 'expired'
                                    : ($expiryDate->startOfDay()->lte($today->addDays($alertDays))
                                        ? 'soon'
                                        : 'ok'));
                    @endphp
                    <tr class="border-t border-line">
                        <td class="px-4 py-3">
                            {{ $level->variant->product->name }}@if ($level->variant->options)
                                — {{ implode(' / ', $level->variant->options) }}
                            @endif
                            <div class="font-mono text-xs text-ink-muted">{{ $level->variant->sku }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $level->warehouse->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $level->batch->batch_number }}</td>
                        <td class="px-4 py-3">{{ $expiryDate?->translatedFormat('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-end font-medium">{{ $level->quantity }}
                            {{ $level->variant->product->unit?->symbol }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($state) {
                                'expired' => 'danger',
                                'soon' => 'warning',
                                default => 'success',
                            }">
                                {{ __('inventory.stock.expiry.' . $state) }}
                            </x-admin.badge>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-ink-muted">
                                {{ __('admin.common.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $levels->links() }}</div>
    </x-layouts.admin>
