<x-layouts.admin :title="__('auth.dashboard_title')">
    <x-admin.page-header :title="__('auth.dashboard_title')" :subtitle="__('auth.account_welcome', ['name' => $user->name])" />

    <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-ink-muted">{{ __('auth.dashboard_roles') }}</dt>
                <dd class="mt-1">{{ $roleLabels->implode(', ') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">{{ __('auth.dashboard_branch') }}</dt>
                <dd class="mt-1">{{ $activeBranch?->name ?? __('auth.dashboard_all_branches') }}</dd>
            </div>
        </dl>

        <p class="mt-6 text-sm text-ink-muted">{{ __('auth.dashboard_coming_soon') }}</p>
    </section>
</x-layouts.admin>
