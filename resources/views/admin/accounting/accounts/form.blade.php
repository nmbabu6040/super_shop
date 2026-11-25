@php
    $isEditing = $account !== null;
    $pageTitle = $isEditing ? __('accounting.accounts.edit_title') : __('accounting.accounts.create_title');
    $typeIsLocked = $isEditing && ($account->lines_count > 0 || $account->children_count > 0 || $account->isSystem());
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('accounting.accounts.title'), route('admin.accounts.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST"
        action="{{ $isEditing ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}"
        x-data="{ busy: false, hasParent: {{ old('parent_id', $account?->parent_id) ? 'true' : 'false' }} }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($isEditing)
                    <div>
                        <p class="text-sm font-medium">{{ __('accounting.fields.code') }}</p>
                        <p class="mt-1 font-mono text-sm">{{ $account->code }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('accounting.accounts.code_locked') }}</p>
                    </div>
                @else
                    <x-admin.input name="code" :label="__('accounting.fields.code')" :required="true" :help="__('accounting.accounts.code_help')" />
                @endif

                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$account?->name" :required="true" />

                <div x-on:change="hasParent = $event.target.value !== ''">
                    <x-admin.select name="parent_id" :label="__('accounting.fields.parent')" :options="$parentOptions" :selected="$account?->parent_id"
                        :placeholder="__('accounting.accounts.no_parent')" :help="__('accounting.accounts.parent_help')" />
                </div>

                <div x-show="! hasParent" x-cloak>
                    @if ($typeIsLocked)
                        <p class="text-sm font-medium">{{ __('accounting.fields.type') }}</p>
                        <p class="mt-1 text-sm">{{ $account->type->label() }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('accounting.accounts.type_locked') }}</p>
                        <input type="hidden" name="type" value="{{ $account->type->value }}">
                    @else
                        <x-admin.select name="type" :label="__('accounting.fields.type')" :options="$typeOptions" :selected="$account?->type->value"
                            :placeholder="__('admin.common.select')" :help="__('accounting.accounts.type_help')" />
                    @endif
                </div>

                <div class="flex flex-col justify-end gap-2 pb-2 sm:col-span-2">
                    <x-admin.checkbox name="is_postable" :label="__('accounting.accounts.postable_label')" :checked="$account?->is_postable ?? true" />
                    <p class="text-xs text-ink-muted">{{ __('accounting.accounts.postable_help') }}</p>
                    <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$account?->is_active ?? true" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.accounts.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
