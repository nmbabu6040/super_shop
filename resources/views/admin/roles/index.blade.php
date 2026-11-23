<x-layouts.admin :title="__('roles.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('roles.title'), null]]">
    <x-admin.page-header :title="__('roles.title')">
        @can('create', \Spatie\Permission\Models\Role::class)
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('roles.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('roles.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('roles.type') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('roles.permissions_count') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('roles.users_count') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    @php($isSystem = in_array($role->name, $systemRoleNames, true))
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-medium">
                            {{ \App\Enums\RoleName::tryFrom($role->name)?->label() ?? $role->name }}
                            <span class="ms-2 font-mono text-xs text-ink-muted">{{ $role->name }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$isSystem ? 'info' : 'neutral'">
                                {{ $isSystem ? __('roles.system') : __('roles.custom') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3 text-end">{{ $role->permissions_count }}</td>
                        <td class="px-4 py-3 text-end">{{ $role->users_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $role)
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-ghost">
                                        <x-ui.icon name="pencil" class="size-4" />
                                        {{ __('admin.common.edit') }}
                                    </a>
                                @endcan
                                @if (!$isSystem && $role->users_count === 0)
                                    @can('delete', $role)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                            data-confirm="{{ __('roles.confirm_delete') }}"
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
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
