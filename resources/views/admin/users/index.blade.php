<x-layouts.admin :title="__('users.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('users.title'), null]]">
    <x-admin.page-header :title="__('users.title')">
        @can('create', \App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('users.create_title') }}
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
            <label for="role"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('users.fields.role') }}</label>
            <select id="role" name="role"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($roleNames as $roleName)
                    <option value="{{ $roleName }}" @selected($roleFilter === $roleName)>
                        {{ \App\Enums\RoleName::tryFrom($roleName)?->label() ?? $roleName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.status') }}</label>
            <select id="status" name="status"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($branches->count() > 1)
            <div>
                <label for="branch"
                    class="mb-1 block text-xs font-medium text-ink-muted">{{ __('users.fields.branches') }}</label>
                <select id="branch" name="branch"
                    class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchFilter === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
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
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('users.fields.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('users.fields.roles') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('users.fields.branches') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('users.fields.last_login') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staffUsers as $staffUser)
                    <tr class="border-t border-line align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $staffUser->name }}</div>
                            <div class="text-xs text-ink-muted">{{ $staffUser->email }}</div>
                            @if ($staffUser->trashed())
                                <x-admin.badge tone="danger"
                                    class="mt-1">{{ __('admin.common.deleted_badge') }}</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($staffUser->roles as $role)
                                    <x-admin.badge>{{ \App\Enums\RoleName::tryFrom($role->name)?->label() ?? $role->name }}</x-admin.badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($staffUser->has_all_branch_access)
                                <x-admin.badge tone="info">{{ __('users.all_branches_badge') }}</x-admin.badge>
                            @else
                                {{ $staffUser->branches->pluck('name')->implode(', ') ?: '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($staffUser->status->value) {
                                'active' => 'success',
                                'pending' => 'warning',
                                default => 'danger',
                            }">{{ $staffUser->status->label() }}</x-admin.badge>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">
                            {{ $staffUser->last_login_at?->diffForHumans() ?? __('users.never') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($staffUser->trashed())
                                    @can('restore', $staffUser)
                                        <form method="POST" action="{{ route('admin.users.restore', $staffUser) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost">
                                                <x-ui.icon name="rotate" class="size-4" />
                                                {{ __('admin.common.restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $staffUser)
                                        <a href="{{ route('admin.users.edit', $staffUser) }}" class="btn btn-ghost">
                                            <x-ui.icon name="pencil" class="size-4" />
                                            {{ __('admin.common.edit') }}
                                        </a>
                                    @endcan
                                    @can('sendInvitation', $staffUser)
                                        <form method="POST" action="{{ route('admin.users.invitation', $staffUser) }}">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-ghost">{{ __('users.resend_invitation') }}</button>
                                        </form>
                                    @endcan
                                    @can('sendPasswordReset', $staffUser)
                                        <form method="POST"
                                            action="{{ route('admin.users.password-reset', $staffUser) }}">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-ghost">{{ __('users.send_reset_link') }}</button>
                                        </form>
                                    @endcan
                                    @can('suspend', $staffUser)
                                        <form method="POST" action="{{ route('admin.users.suspend', $staffUser) }}"
                                            data-confirm="{{ __('users.confirm_suspend') }}"
                                            data-confirm-title="{{ __('admin.common.confirm_title') }}"
                                            data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                                            data-confirm-no="{{ __('admin.common.confirm_no') }}">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-ghost text-amber-700">{{ __('users.suspend') }}</button>
                                        </form>
                                    @endcan
                                    @can('reactivate', $staffUser)
                                        <form method="POST" action="{{ route('admin.users.reactivate', $staffUser) }}">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-ghost">{{ __('users.reactivate') }}</button>
                                        </form>
                                    @endcan
                                    @can('delete', $staffUser)
                                        <form method="POST" action="{{ route('admin.users.destroy', $staffUser) }}"
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

    <div class="mt-4">{{ $staffUsers->links() }}</div>
</x-layouts.admin>
