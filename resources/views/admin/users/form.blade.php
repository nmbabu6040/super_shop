@php
    $isEditing = $staffUser !== null;
    $pageTitle = $isEditing ? __('users.edit_title') : __('users.create_title');
    $localeOptions = collect(config('locales.supported'))->map(fn(array $meta): string => $meta['native_name'])->all();
    $selectedRoles = old('roles', $isEditing ? $staffUser->getRoleNames()->all() : []);
    $selectedBranchIds = array_map(
        'intval',
        old('branch_ids', $isEditing ? $staffUser->branches->pluck('id')->all() : []),
    );
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('users.title'), route('admin.users.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST" action="{{ $isEditing ? route('admin.users.update', $staffUser) : route('admin.users.store') }}"
        x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name" :label="__('users.fields.name')" :value="$staffUser?->name" :required="true" />
                <x-admin.input name="email" type="email" :label="__('users.fields.email')" :value="$staffUser?->email" :required="true" />
                <x-admin.input name="username" :label="__('users.fields.username')" :value="$staffUser?->username" :help="__('users.username_help')" />
                <x-admin.input name="phone" type="tel" :label="__('users.fields.phone')" :value="$staffUser?->phone" />
                <x-admin.select name="preferred_locale" :label="__('users.fields.preferred_locale')" :options="$localeOptions" :selected="$staffUser?->preferred_locale ?? config('app.locale')" />
            </div>
            @unless ($isEditing)
                <p class="mt-4 rounded-xl bg-surface p-3 text-sm text-ink-muted">{{ __('users.invite_note') }}</p>
            @endunless
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="text-lg font-semibold">{{ __('users.fields.roles') }}</h2>
            <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('users.roles_help') }}</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($assignableRoles as $roleName)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $roleName }}"
                            class="size-4 rounded border-line accent-brand-600" @checked(in_array($roleName, $selectedRoles, true))>
                        {{ \App\Enums\RoleName::tryFrom($roleName)?->label() ?? $roleName }}
                    </label>
                @endforeach
            </div>
            @error('roles')
                <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="text-lg font-semibold">{{ __('users.fields.branches') }}</h2>
            <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('users.branches_help') }}</p>
            @if ($canGrantAllBranches)
                <div class="mb-4">
                    <x-admin.checkbox name="has_all_branch_access" :label="__('users.all_branches_access')" :checked="$staffUser?->has_all_branch_access ?? false" />
                    <p class="mt-1 text-xs text-ink-muted">{{ __('users.all_branches_access_help') }}</p>
                </div>
            @endif
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($branches as $branch)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}"
                            class="size-4 rounded border-line accent-brand-600" @checked(in_array($branch->id, $selectedBranchIds, true))>
                        {{ $branch->name }}
                    </label>
                @endforeach
            </div>
            @error('branch_ids')
                <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
