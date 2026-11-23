<x-layouts.admin :title="__('catalog.units.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('catalog.units.title'), null]]">
    <x-admin.page-header :title="__('catalog.units.title')">
        @can('create', \App\Models\Unit::class)
            <a href="{{ route('admin.units.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('catalog.units.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('catalog.units.code') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.units.symbol') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.units.decimal') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.units.base_unit') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($units as $unit)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">{{ $unit->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $unit->name }}</td>
                        <td class="px-4 py-3">{{ $unit->symbol }}</td>
                        <td class="px-4 py-3">
                            {{ $unit->is_decimal ? __('admin.common.yes') . ' (' . $unit->decimal_places . ')' : __('admin.common.no') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($unit->baseUnit)
                                1 {{ $unit->symbol }} = {{ rtrim(rtrim($unit->conversion_factor, '0'), '.') }}
                                {{ $unit->baseUnit->symbol }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$unit->is_active ? 'success' : 'neutral'">
                                {{ $unit->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $unit)
                                    <a href="{{ route('admin.units.edit', $unit) }}" class="btn btn-ghost">
                                        <x-ui.icon name="pencil" class="size-4" />
                                        {{ __('admin.common.edit') }}
                                    </a>
                                @endcan
                                @can('delete', $unit)
                                    <form method="POST" action="{{ route('admin.units.destroy', $unit) }}"
                                        data-confirm="{{ __('catalog.units.confirm_delete') }}"
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
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
