@php
    $isEditing = $taxRate !== null;
    $pageTitle = $isEditing ? __('tax.edit_title') : __('tax.create_title');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('tax.title'), route('admin.tax-rates.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST"
        action="{{ $isEditing ? route('admin.tax-rates.update', $taxRate) : route('admin.tax-rates.store') }}"
        x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$taxRate?->name" :required="true" />
                <x-admin.input name="rate" type="number" step="0.01" min="0" max="100"
                    :label="__('tax.rate')" :value="$taxRate ? rtrim(rtrim($taxRate->rate, '0'), '.') : ''" :required="true" :help="__('tax.rate_help')" />
                <div class="flex flex-col justify-end gap-2 pb-2">
                    <x-admin.checkbox name="is_default" :label="__('tax.default')" :checked="$taxRate?->isDefault() ?? false" />
                    <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$taxRate?->is_active ?? true" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.tax-rates.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
