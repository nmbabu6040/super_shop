@php
    $isEditing = $warehouse !== null;
    $pageTitle = $isEditing ? __('admin.warehouses.edit_title') : __('admin.warehouses.create_title');
    $typeOptions = collect($types)->mapWithKeys(fn($type): array => [$type->value => $type->label()])->all();
    $branchOptions = $branches->pluck('name', 'id')->all();
    $managerOptions = $managers->pluck('name', 'id')->all();
    $initialType = old('type', 'storage');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('admin.warehouses.title'), route('admin.warehouses.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST"
        action="{{ $isEditing ? route('admin.warehouses.update', $warehouse) : route('admin.warehouses.store') }}"
        x-data="{ busy: false, warehouseType: '{{ $initialType }}' }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name" :label="__('admin.warehouses.name')" :value="$warehouse?->name" :required="true" />

                @if ($isEditing)
                    {{-- Code, type and branch are fixed after creation: shown, never submitted. --}}
                    <dl class="grid grid-cols-3 gap-3 rounded-xl bg-surface p-3 text-sm sm:col-span-2">
                        <div>
                            <dt class="text-xs text-ink-muted">{{ __('admin.warehouses.code') }}</dt>
                            <dd class="font-mono">{{ $warehouse->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">{{ __('admin.warehouses.type') }}</dt>
                            <dd>{{ $warehouse->type->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">{{ __('admin.warehouses.branch') }}</dt>
                            <dd>{{ $warehouse->branch?->name ?? __('admin.warehouses.central') }}</dd>
                        </div>
                        <p class="col-span-3 text-xs text-ink-muted">{{ __('admin.warehouses.immutable_help') }}</p>
                    </dl>
                @else
                    <x-admin.input name="code" :label="__('admin.warehouses.code')" :required="true" :help="__('admin.warehouses.code_help')"
                        style="text-transform: uppercase" />
                    <x-admin.select name="type" :label="__('admin.warehouses.type')" :options="$typeOptions" :selected="$initialType"
                        :required="true" x-model="warehouseType" />
                    {{-- A central warehouse has no branch: the select is hidden and disabled (so it is not submitted). --}}
                    <div x-show="warehouseType !== 'central'">
                        <x-admin.select name="branch_id" :label="__('admin.warehouses.branch')" :options="$branchOptions" :placeholder="__('admin.common.select')"
                            x-bind:disabled="warehouseType === 'central'" />
                    </div>
                @endif

                <x-admin.select name="manager_id" :label="__('admin.warehouses.manager')" :options="$managerOptions" :selected="$warehouse?->manager_id"
                    :placeholder="__('admin.common.none')" />
                <x-admin.input name="phone" type="tel" :label="__('admin.warehouses.phone')" :value="$warehouse?->phone" />
                <div class="sm:col-span-2">
                    <x-admin.input name="address_line" :label="__('admin.warehouses.address_line')" :value="$warehouse?->address_line" />
                </div>
                <x-admin.checkbox name="is_sellable" :label="__('admin.warehouses.sellable')" :checked="$warehouse?->is_sellable ?? false" />
                <x-admin.checkbox name="is_active" :label="__('admin.warehouses.active')" :checked="$warehouse?->is_active ?? true" />
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.warehouses.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
