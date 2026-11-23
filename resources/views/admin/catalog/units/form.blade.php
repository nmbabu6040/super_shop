@php
    $isEditing = $unit !== null;
    $pageTitle = $isEditing ? __('catalog.units.edit_title') : __('catalog.units.create_title');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('catalog.units.title'), route('admin.units.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST" action="{{ $isEditing ? route('admin.units.update', $unit) : route('admin.units.store') }}"
        x-data="{ busy: false, isDecimal: {{ old('is_decimal', $unit?->is_decimal ?? false) ? 'true' : 'false' }} }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($isEditing)
                    <div>
                        <p class="text-sm font-medium">{{ __('catalog.units.code') }}</p>
                        <p class="mt-1 font-mono text-sm">{{ $unit->code }}</p>
                    </div>
                @else
                    <x-admin.input name="code" :label="__('catalog.units.code')" :required="true" :help="__('catalog.units.code_help')" />
                @endif
                <x-admin.input name="symbol" :label="__('catalog.units.symbol')" :value="$unit?->symbol" :required="true" />
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$unit?->name" :required="true" />

                <div class="sm:col-span-2">
                    <input type="hidden" name="is_decimal" value="0">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_decimal" value="1" x-model="isDecimal"
                            class="size-4 rounded border-line accent-brand-600">
                        {{ __('catalog.units.decimal') }}
                    </label>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('catalog.units.decimal_help') }}</p>
                </div>
                <div x-show="isDecimal" x-cloak>
                    <x-admin.input name="decimal_places" type="number" min="0" max="6" :label="__('catalog.units.decimal_places')"
                        :value="$unit?->decimal_places ?? 0" />
                </div>

                <x-admin.select name="base_unit_id" :label="__('catalog.units.base_unit')" :options="$baseUnitOptions" :selected="$unit?->base_unit_id"
                    :placeholder="__('catalog.units.none_base')" :help="__('catalog.units.base_help')" />
                <x-admin.input name="conversion_factor" type="number" step="0.000001" min="0" :label="__('catalog.units.conversion_factor')"
                    :value="$unit && $unit->base_unit_id ? rtrim(rtrim($unit->conversion_factor, '0'), '.') : ''" :help="__('catalog.units.conversion_help')" />
                <div class="flex items-end pb-2">
                    <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$unit?->is_active ?? true" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.units.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
