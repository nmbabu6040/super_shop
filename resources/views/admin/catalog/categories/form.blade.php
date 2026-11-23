@php
    $isEditing = $category !== null;
    $pageTitle = $isEditing ? __('catalog.categories.edit_title') : __('catalog.categories.create_title');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('catalog.categories.title'), route('admin.categories.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST"
        action="{{ $isEditing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
        enctype="multipart/form-data" x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false"
        class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$category?->name" :required="true" />
                <x-admin.select name="parent_id" :label="__('catalog.categories.parent')" :options="$parentOptions" :selected="$category?->parent_id" :placeholder="__('catalog.categories.no_parent')"
                    :help="__('catalog.categories.parent_help')" />
                <x-admin.input name="slug" :label="__('catalog.slug')" :value="$category?->slug" :help="__('catalog.slug_help')" />
                <x-admin.input name="sort_order" type="number" min="0" :label="__('catalog.sort_order')" :value="$category?->sort_order ?? 0" />
                <div class="flex items-end pb-2">
                    <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$category?->is_active ?? true" />
                </div>
                <x-admin.translatable-input name="description" :label="__('catalog.description')" :values="$category?->description"
                    :multiline="true" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <x-admin.file-input name="image" :label="__('catalog.image')" :current-path="$category?->image_path" :help="__('catalog.image_help')"
                :remove-label="__('catalog.remove_image')" />
        </section>

        <details class="rounded-card border border-line bg-surface-raised p-6 shadow-card"
            @if ($errors->has('meta_title.*') || $errors->has('meta_description.*')) open @endif>
            <summary class="cursor-pointer text-lg font-semibold">{{ __('catalog.seo') }}</summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="meta_title" :label="__('catalog.meta_title')" :values="$category?->meta_title" />
                <x-admin.translatable-input name="meta_description" :label="__('catalog.meta_description')" :values="$category?->meta_description"
                    :multiline="true" />
            </div>
        </details>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
