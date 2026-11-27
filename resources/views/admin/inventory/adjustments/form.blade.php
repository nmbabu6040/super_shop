@php
    $fieldClasses =
        'w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20';
@endphp
<x-layouts.admin :title="__('inventory.adjustments.create_title')" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('inventory.adjustments.title'), route('admin.stock-adjustments.index')],
    [__('inventory.adjustments.create_title'), null],
]">
    <x-admin.page-header :title="__('inventory.adjustments.create_title')" :subtitle="__('inventory.adjustments.pending_note')" />

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

    <form method="POST" action="{{ route('admin.stock-adjustments.store') }}" x-data="{
        busy: false,
        warehouseId: @js((string) old('warehouse_id', '')),
        lines: @js($initialLines),
        nextUid: {{ count($initialLines) + 1 }},
        searchUrl: @js(route('admin.variants.search')),
        async search(line) {
            if (line.query.trim().length < 2) { line.results = []; return; }
            const params = new URLSearchParams({ q: line.query.trim() });
            if (this.warehouseId) { params.set('warehouse_id', this.warehouseId); }
            const response = await fetch(this.searchUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } });
            if (response.ok) { line.results = await response.json();
                line.open = true; }
        },
        pick(line, result) {
            Object.assign(line, {
                variant_id: result.id,
                label: result.label + ' · ' + result.sku,
                track_batches: result.track_batches,
                track_expiry: result.track_expiry,
                on_hand: result.on_hand,
                query: '',
                results: [],
                open: false,
            });
        },
        clearPick(line) { Object.assign(line, { variant_id: '', label: '', track_batches: false, track_expiry: false, on_hand: '' }); },
        addLine() {
            if (this.lines.length < 100) {
                this.lines.push({ uid: this.nextUid++, variant_id: '', label: '', track_batches: false, track_expiry: false, on_hand: '', quantity_change: '', batch_number: '', expiry_date: '', unit_cost: '', notes: '', query: '', results: [], open: false });
            }
        },
        removeLine(index) { if (this.lines.length > 1) { this.lines.splice(index, 1); } },
    }"
        x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="warehouse_id"
                        class="mb-1.5 block text-sm font-medium">{{ __('inventory.fields.warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="{{ $fieldClasses }}" x-model="warehouseId"
                        required>
                        <option value="">{{ __('admin.common.select') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-admin.input name="adjustment_date" type="date" :label="__('inventory.fields.date')" :value="old('adjustment_date', $defaultDate)"
                    :required="true" />
                <div>
                    <label for="reason"
                        class="mb-1.5 block text-sm font-medium">{{ __('inventory.fields.reason') }}</label>
                    <select id="reason" name="reason" class="{{ $fieldClasses }}" required>
                        <option value="">{{ __('admin.common.select') }}</option>
                        @foreach ($reasons as $reason)
                            <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <x-admin.input name="notes" :label="__('inventory.fields.notes')" />
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-1 text-lg font-semibold">{{ __('inventory.fields.lines') }}</h2>
            <p class="mb-4 text-xs text-ink-muted">{{ __('inventory.adjustments.quantity_help') }}</p>

            <div class="space-y-3">
                <template x-for="(line, index) in lines" x-bind:key="line.uid">
                    <div class="space-y-3 rounded-xl border border-line p-3">
                        <input type="hidden" x-bind:name="`lines[${index}][variant_id]`"
                            x-bind:value="line.variant_id">

                        <div class="relative">
                            <div x-show="line.variant_id" class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium" x-text="line.label"></span>
                                <span class="text-xs text-ink-muted" x-show="line.on_hand !== ''"
                                    x-text="`{{ __('inventory.adjustments.on_hand') }}: ` + line.on_hand"></span>
                                <button type="button"
                                    class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300"
                                    x-on:click="clearPick(line)">{{ __('inventory.adjustments.change_product') }}</button>
                            </div>
                            <div x-show="! line.variant_id">
                                <input type="search" class="{{ $fieldClasses }}"
                                    placeholder="{{ __('inventory.adjustments.search_product') }}" x-model="line.query"
                                    x-on:input.debounce.300ms="search(line)" autocomplete="off">
                                <ul x-show="line.open && line.results.length" x-cloak
                                    class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-line bg-surface-raised shadow-card">
                                    <template x-for="result in line.results" x-bind:key="result.id">
                                        <li>
                                            <button type="button"
                                                class="block w-full px-3 py-2 text-start text-sm hover:bg-surface"
                                                x-on:click="pick(line, result)"
                                                x-text="result.label + ' · ' + result.sku + (result.barcode ? ' · ' + result.barcode : '')"></button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium">{{ __('inventory.fields.quantity_change') }}</label>
                                <input type="text" inputmode="decimal" class="{{ $fieldClasses }}"
                                    x-model="line.quantity_change" x-bind:name="`lines[${index}][quantity_change]`">
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium">{{ __('inventory.fields.unit_cost') }}</label>
                                <input type="text" inputmode="decimal" class="{{ $fieldClasses }}"
                                    x-model="line.unit_cost" x-bind:name="`lines[${index}][unit_cost]`">
                                <p class="mt-1 text-xs text-ink-muted">{{ __('inventory.adjustments.cost_help') }}</p>
                            </div>
                            <div x-show="line.track_batches" x-cloak>
                                <label
                                    class="mb-1 block text-xs font-medium">{{ __('inventory.fields.batch') }}</label>
                                <input type="text" class="{{ $fieldClasses }}" x-model="line.batch_number"
                                    x-bind:name="`lines[${index}][batch_number]`"
                                    x-bind:disabled="!line.track_batches">
                            </div>
                            <div x-show="line.track_batches && line.track_expiry" x-cloak>
                                <label
                                    class="mb-1 block text-xs font-medium">{{ __('inventory.fields.expiry') }}</label>
                                <input type="date" class="{{ $fieldClasses }}" x-model="line.expiry_date"
                                    x-bind:name="`lines[${index}][expiry_date]`"
                                    x-bind:disabled="!(line.track_batches && line.track_expiry)">
                            </div>
                        </div>

                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <label
                                    class="mb-1 block text-xs font-medium">{{ __('inventory.fields.line_notes') }}</label>
                                <input type="text" class="{{ $fieldClasses }}" x-model="line.notes"
                                    x-bind:name="`lines[${index}][notes]`">
                            </div>
                            <button type="button" class="btn btn-ghost text-red-600" x-on:click="removeLine(index)"
                                x-bind:disabled="lines.length <= 1">
                                <x-ui.icon name="trash" class="size-4" />
                                {{ __('inventory.adjustments.remove_line') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" class="btn btn-ghost mt-4" x-on:click="addLine()">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('inventory.adjustments.add_line') }}
            </button>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('inventory.adjustments.submit') }}</button>
            <a href="{{ route('admin.stock-adjustments.index') }}"
                class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
