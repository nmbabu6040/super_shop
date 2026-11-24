<x-layouts.admin :title="__('products.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('products.title'), null]]">
    <x-admin.page-header :title="__('products.title')">
        @can('create', \App\Models\Product::class)
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('products.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="search"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.search') }}</label>
            <input id="search" name="search" type="search" value="{{ $searchTerm }}"
                placeholder="{{ __('products.search_placeholder') }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="category_id"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('products.fields.category') }}</label>
            <select id="category_id" name="category_id"
                class="max-w-48 rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($categoryOptions as $categoryId => $categoryLabel)
                    <option value="{{ $categoryId }}" @selected($categoryFilter === $categoryId)>{{ $categoryLabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="brand_id"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('products.fields.brand') }}</label>
            <select id="brand_id" name="brand_id"
                class="max-w-40 rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected($brandFilter === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('products.fields.product_type') }}</label>
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
        <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('products.fields.product') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('products.fields.category') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('products.fields.sku') }} /
                        {{ __('products.fields.barcode') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('products.fields.selling_price') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php($variant = $product->defaultVariant)
                    <tr class="border-t border-line align-top">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($product->primaryImage)
                                    <img src="{{ \App\Support\Media\PublicImageStore::url($product->primaryImage->path) }}"
                                        alt="" class="size-10 rounded-lg object-cover" loading="lazy">
                                @else
                                    <span
                                        class="flex size-10 items-center justify-center rounded-lg bg-surface text-ink-muted">
                                        <x-ui.icon name="shopping-bag" class="size-5" />
                                    </span>
                                @endif
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="flex flex-wrap gap-1 pt-0.5">
                                        @if ($product->isMedicine())
                                            <x-admin.badge
                                                tone="info">{{ __('products.types.medicine') }}</x-admin.badge>
                                        @endif
                                        @if ($product->trashed())
                                            <x-admin.badge
                                                tone="danger">{{ __('admin.common.deleted_badge') }}</x-admin.badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $product->category?->name }}
                            @if ($product->brand)
                                <div class="text-xs text-ink-muted">{{ $product->brand->name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-mono text-xs">{{ $variant?->sku }}</div>
                            <div class="font-mono text-xs text-ink-muted">{{ $variant?->primaryBarcode?->barcode }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-end">
                            {{ \App\Support\Money\MoneyFormatter::format($variant?->selling_price) }}
                            <div class="text-xs text-ink-muted">/ {{ $product->unit?->symbol }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$product->is_active ? 'success' : 'neutral'">
                                {{ $product->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($product->trashed())
                                    @can('restore', $product)
                                        <form method="POST" action="{{ route('admin.products.restore', $product) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost">
                                                <x-ui.icon name="rotate" class="size-4" />
                                                {{ __('admin.common.restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $product)
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-ghost">
                                            <x-ui.icon name="pencil" class="size-4" />
                                            {{ __('admin.common.edit') }}
                                        </a>
                                    @endcan
                                    @can('delete', $product)
                                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
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

    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.admin>
