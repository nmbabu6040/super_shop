<x-layouts.admin :title="__('accounting.fiscal.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('accounting.fiscal.title'), null]]">
    <x-admin.page-header :title="__('accounting.fiscal.title')" :subtitle="__('accounting.fiscal.subtitle')">
        @can('create', \App\Models\FiscalYear::class)
            <a href="{{ route('admin.fiscal-years.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('accounting.fiscal.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="space-y-6">
        @forelse ($fiscalYears as $fiscalYear)
            <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $fiscalYear->name }}</h2>
                        <p class="text-sm text-ink-muted">
                            {{ $fiscalYear->starts_on->translatedFormat('d M Y') }} –
                            {{ $fiscalYear->ends_on->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    @can('delete', $fiscalYear)
                        <form method="POST" action="{{ route('admin.fiscal-years.destroy', $fiscalYear) }}"
                            data-confirm="{{ __('accounting.fiscal.confirm_delete') }}"
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

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($fiscalYear->periods as $period)
                        <div class="rounded-xl border border-line p-3">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm">{{ $period->name }}</span>
                                <x-admin.badge :tone="$period->isOpen() ? 'success' : 'neutral'">{{ $period->status->label() }}</x-admin.badge>
                            </div>
                            <div class="mt-2">
                                @if ($period->isOpen())
                                    @can('closePeriod', \App\Models\FiscalYear::class)
                                        <form method="POST"
                                            action="{{ route('admin.fiscal-years.periods.close', $period) }}"
                                            data-confirm="{{ __('accounting.fiscal.confirm_close') }}"
                                            data-confirm-title="{{ __('admin.common.confirm_title') }}"
                                            data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                                            data-confirm-no="{{ __('admin.common.confirm_no') }}">
                                            @csrf
                                            <button type="submit"
                                                class="text-xs font-medium text-amber-700 hover:underline">{{ __('accounting.fiscal.close_period') }}</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('reopenPeriod', \App\Models\FiscalYear::class)
                                        <form method="POST"
                                            action="{{ route('admin.fiscal-years.periods.reopen', $period) }}"
                                            data-confirm="{{ __('accounting.fiscal.confirm_reopen') }}"
                                            data-confirm-title="{{ __('admin.common.confirm_title') }}"
                                            data-confirm-yes="{{ __('admin.common.confirm_yes') }}"
                                            data-confirm-no="{{ __('admin.common.confirm_no') }}">
                                            @csrf
                                            <button type="submit"
                                                class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">{{ __('accounting.fiscal.reopen_period') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-card border border-line bg-surface-raised p-10 text-center text-ink-muted shadow-card">
                {{ __('admin.common.no_results') }}
            </p>
        @endforelse
    </div>
</x-layouts.admin>
