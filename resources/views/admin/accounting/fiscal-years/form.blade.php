<x-layouts.admin :title="__('accounting.fiscal.create_title')" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('accounting.fiscal.title'), route('admin.fiscal-years.index')],
    [__('accounting.fiscal.create_title'), null],
]">
    <x-admin.page-header :title="__('accounting.fiscal.create_title')" />

    <form method="POST" action="{{ route('admin.fiscal-years.store') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name" :label="__('accounting.fiscal.name')" :required="true" />
                <x-admin.input name="starts_on" type="date" :label="__('accounting.fiscal.starts_on')" :required="true" :help="__('accounting.fiscal.starts_on_help')" />
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.fiscal-years.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
