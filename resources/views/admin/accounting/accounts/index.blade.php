<x-layouts.admin :title="__('accounting.accounts.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('accounting.accounts.title'), null]]">
    <x-admin.page-header :title="__('accounting.accounts.title')">
        @can('create', \App\Models\Account::class)
            <a href="{{ route('admin.accounts.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('accounting.accounts.create_title') }}
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
            <label for="type"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('accounting.fields.type') }}</label>
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
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.accounts.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('accounting.fields.code') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('accounting.fields.type') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('accounting.fields.kind') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($account = $row['account'])
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">{{ $account->code }}</td>
                        <td class="px-4 py-3 {{ $account->is_postable ? '' : 'font-semibold' }}"
                            style="padding-inline-start: {{ 1 + $row['depth'] * 1.5 }}rem">
                            {{ $account->name }}
                            @if ($account->isSystem())
                                <x-admin.badge tone="info"
                                    class="ms-2">{{ __('accounting.accounts.system') }}</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $account->type->label() }}</td>
                        <td class="px-4 py-3">
                            {{ $account->is_postable ? __('accounting.accounts.postable') : __('accounting.accounts.group') }}
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$account->is_active ? 'success' : 'neutral'">
                                {{ $account->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $account)
                                    <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-ghost">
                                        <x-ui.icon name="pencil" class="size-4" />
                                        {{ __('admin.common.edit') }}
                                    </a>
                                @endcan
                                @can('delete', $account)
                                    @if (!$account->isSystem() && $account->children_count === 0)
                                        <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}"
                                            data-confirm="{{ __('accounting.accounts.confirm_delete') }}"
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
</x-layouts.admin>
