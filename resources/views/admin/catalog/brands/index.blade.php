<x-layouts.admin :title="__('catalog.brands.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('catalog.brands.title'), null]]">
    <x-admin.page-header :title="__('catalog.brands.title')">
        @can('create', \App\Models\Brand::class)
            <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('catalog.brands.create_title') }}
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
        <a href="{{ route('admin.brands.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('catalog.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.slug') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('catalog.brands.website') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($brands as $brand)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-medium">
                            <div class="flex items-center gap-3">
                                @if ($brand->logo_path)
                                    <img src="{{ \App\Support\Media\PublicImageStore::url($brand->logo_path) }}"
                                        alt="" class="size-8 rounded-lg object-contain" loading="lazy">
                                @endif
                                <span>{{ $brand->name }}</span>
                                @if ($brand->trashed())
                                    <x-admin.badge
                                        tone="danger">{{ __('admin.common.deleted_badge') }}</x-admin.badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $brand->slug }}</td>
                        <td class="px-4 py-3">
                            @if ($brand->website_url)
                                <a href="{{ $brand->website_url }}" target="_blank" rel="noopener noreferrer nofollow"
                                    class="text-brand-700 hover:underline dark:text-brand-300">{{ parse_url($brand->website_url, PHP_URL_HOST) }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$brand->is_active ? 'success' : 'neutral'">
                                {{ $brand->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($brand->trashed())
                                    @can('restore', $brand)
                                        <form method="POST" action="{{ route('admin.brands.restore', $brand) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost">
                                                <x-ui.icon name="rotate" class="size-4" />
                                                {{ __('admin.common.restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $brand)
                                        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-ghost">
                                            <x-ui.icon name="pencil" class="size-4" />
                                            {{ __('admin.common.edit') }}
                                        </a>
                                    @endcan
                                    @can('delete', $brand)
                                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}"
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
                        <td colspan="5" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $brands->links() }}</div>
</x-layouts.admin>
