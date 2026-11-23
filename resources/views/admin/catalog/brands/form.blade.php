@php
    $isEditing = $brand !== null;
    $pageTitle = $isEditing ? __('catalog.brands.edit_title') : __('catalog.brands.create_title');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('catalog.brands.title'), route('admin.brands.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST" action="{{ $isEditing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
        enctype="multipart/form-data" x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false"
        class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$brand?->name" :required="true" />
                <x-admin.input name="slug" :label="__('catalog.slug')" :value="$brand?->slug" :help="__('catalog.slug_help')" />
                <x-admin.input name="website_url" type="url" :label="__('catalog.brands.website')" :value="$brand?->website_url"
                    placeholder="https://" />
                <x-admin.input name="sort_order" type="number" min="0" :label="__('catalog.sort_order')" :value="$brand?->sort_order ?? 0" />
                <div class="flex items-end pb-2">
                    <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$brand?->is_active ?? true" />
                </div>
                <x-admin.translatable-input name="description" :label="__('catalog.description')" :values="$brand?->description"
                    :multiline="true" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <x-admin.file-input name="logo" :label="__('catalog.brands.logo')" :current-path="$brand?->logo_path" :help="__('catalog.image_help')"
                :remove-label="__('catalog.remove_image')" />
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
