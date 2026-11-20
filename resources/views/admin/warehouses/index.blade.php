<x-layouts.admin :title="__('admin.warehouses.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('admin.warehouses.title'), null]]">
    <x-admin.page-header :title="__('admin.warehouses.title')">
        @can('create', \App\Models\Warehouse::class)
            <a href="{{ route('admin.warehouses.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('admin.warehouses.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="search"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.search') }}</label>
            <input id="search" name="search" type="search" value="{{ $searchTerm }}"
                placeholder="{{ __('admin.common.search_placeholder') }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="branch"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.warehouses.branch') }}</label>
            <select id="branch" name="branch"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                <option value="central" @selected($branchFilter === 'central')>{{ __('admin.warehouses.central') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchFilter === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.warehouses.type') }}</label>
            <select id="type" name="type"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($typeFilter === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="active"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.status') }}</label>
            <select id="active" name="active"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                <option value="1" @selected($activeFilter === '1')>{{ __('admin.common.active') }}</option>
                <option value="0" @selected($activeFilter === '0')>{{ __('admin.common.inactive') }}</option>
            </select>
        </div>
        @if ($canSeeDeleted)
            <label class="inline-flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" name="trashed" value="1" class="size-4 rounded border-line accent-brand-600"
                    @checked($showDeleted)>
                {{ __('admin.common.show_deleted') }}
            </label>
        @endif
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.warehouses.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.warehouses.code') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.warehouses.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.warehouses.branch') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.warehouses.type') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($warehouses as $warehouse)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">{{ $warehouse->code }}</td>
                        <td class="px-4 py-3 font-medium">
                            <div class="flex items-center gap-2">
                                <span>{{ $warehouse->name }}</span>
                                @if ($warehouse->default_for_branch_id !== null)
                                    <x-admin.badge
                                        tone="info">{{ __('admin.warehouses.default_badge') }}</x-admin.badge>
                                @endif
                                @if ($warehouse->trashed())
                                    <x-admin.badge
                                        tone="danger">{{ __('admin.common.deleted_badge') }}</x-admin.badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $warehouse->branch?->name ?? __('admin.warehouses.central') }}</td>
                        <td class="px-4 py-3">{{ $warehouse->type->label() }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$warehouse->is_active ? 'success' : 'neutral'">
                                {{ $warehouse->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($warehouse->trashed())
                                    @can('restore', $warehouse)
                                        <form method="POST" action="{{ route('admin.warehouses.restore', $warehouse) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost">
                                                <x-ui.icon name="rotate" class="size-4" />
                                                {{ __('admin.common.restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $warehouse)
                                        <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-ghost">
                                            <x-ui.icon name="pencil" class="size-4" />
                                            {{ __('admin.common.edit') }}
                                        </a>
                                    @endcan
                                    @can('delete', $warehouse)
                                        @if ($warehouse->default_for_branch_id === null)
                                            <form method="POST"
                                                action="{{ route('admin.warehouses.destroy', $warehouse) }}"
                                                data-confirm="{{ __('admin.common.confirm_delete') }}"
                                                data-confirm-title="{{ __('admin.common.confirm_title') }}"
                                                data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                                                data-confirm-no="{{ __('admin.common.confirm_no') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-ghost text-red-600">
                                                    <x-ui.icon name="trash" class="size-4" />
                                                    {{ __('admin.common.delete') }}
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                @endif
                            </div>
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

    <div class="mt-4">{{ $warehouses->links() }}</div>
</x-layouts.admin>
