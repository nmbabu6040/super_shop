<x-layouts.admin :title="__('tax.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('tax.title'), null]]">
    <x-admin.page-header :title="__('tax.title')">
        @can('create', \App\Models\TaxRate::class)
            <a href="{{ route('admin.tax-rates.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('tax.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('catalog.name') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('tax.rate') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('tax.products_count') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.common.status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($taxRates as $taxRate)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-medium">
                            {{ $taxRate->name }}
                            @if ($taxRate->isDefault())
                                <x-admin.badge tone="info"
                                    class="ms-2">{{ __('tax.default_badge') }}</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end">{{ rtrim(rtrim($taxRate->rate, '0'), '.') }}%</td>
                        <td class="px-4 py-3 text-end">{{ $taxRate->products_count }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$taxRate->is_active ? 'success' : 'neutral'">
                                {{ $taxRate->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $taxRate)
                                    <a href="{{ route('admin.tax-rates.edit', $taxRate) }}" class="btn btn-ghost">
                                        <x-ui.icon name="pencil" class="size-4" />
                                        {{ __('admin.common.edit') }}
                                    </a>
                                @endcan
                                @can('delete', $taxRate)
                                    @unless ($taxRate->isDefault())
                                        <form method="POST" action="{{ route('admin.tax-rates.destroy', $taxRate) }}"
                                            data-confirm="{{ __('tax.confirm_delete') }}"
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
                                    @endunless
                                @endcan
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
