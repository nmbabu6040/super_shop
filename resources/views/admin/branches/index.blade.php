<x-layouts.admin :title="__('admin.branches.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('admin.branches.title'), null]]">
    <x-admin.page-header :title="__('admin.branches.title')">
        @can('create', \App\Models\Branch::class)
            <a href="{{ route('admin.branches.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('admin.branches.create_title') }}
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
        <a href="{{ route('admin.branches.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-start text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.branches.code') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.branches.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.branches.city') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.branches.manager') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.branches.warehouses_count') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.branches.users_count') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">{{ $branch->code }}</td>
                        <td class="px-4 py-3 font-medium">
                            <div class="flex items-center gap-3">
                                @if ($branch->logo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($branch->logo_path) }}"
                                        alt="" class="size-8 rounded-lg object-cover" loading="lazy">
                                @endif
                                <span>{{ $branch->name }}</span>
                                @if ($branch->is_head_office)
                                    <x-admin.badge
                                        tone="info">{{ __('admin.branches.head_office') }}</x-admin.badge>
                                @endif
                                @if ($branch->trashed())
                                    <x-admin.badge
                                        tone="danger">{{ __('admin.common.deleted_badge') }}</x-admin.badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $branch->city ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $branch->manager?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($branch->status->value) {
                                'active' => 'success',
                                'temporarily_closed' => 'warning',
                                'closed' => 'danger',
                                default => 'neutral',
                            }">{{ $branch->status->label() }}</x-admin.badge>
                        </td>
                        <td class="px-4 py-3 text-end">{{ $branch->warehouses_count }}</td>
                        <td class="px-4 py-3 text-end">{{ $branch->users_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($branch->trashed())
                                    @can('restore', $branch)
                                        <form method="POST" action="{{ route('admin.branches.restore', $branch) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost">
                                                <x-ui.icon name="rotate" class="size-4" />
                                                {{ __('admin.common.restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $branch)
                                        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-ghost">
                                            <x-ui.icon name="pencil" class="size-4" />
                                            {{ __('admin.common.edit') }}
                                        </a>
                                    @endcan
                                    @can('delete', $branch)
                                        <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}"
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
                        <td colspan="8" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $branches->links() }}</div>
</x-layouts.admin>
