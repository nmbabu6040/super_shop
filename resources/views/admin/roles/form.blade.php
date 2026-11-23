@php
    $isEditing = $role !== null;
    $pageTitle = $isEditing ? __('roles.edit_title') : __('roles.create_title');
    $selectedPermissions = old('permissions', $selected);
    $isLocked = $isSuperAdmin || $isCustomer;
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('roles.title'), route('admin.roles.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    @if ($isSuperAdmin)
        <p class="mb-4 rounded-xl bg-surface p-3 text-sm text-ink-muted">{{ __('roles.super_admin_note') }}</p>
    @elseif ($isCustomer)
        <p class="mb-4 rounded-xl bg-surface p-3 text-sm text-ink-muted">{{ __('roles.customer_note') }}</p>
    @endif

    <form method="POST" action="{{ $isEditing ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
        x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="max-w-md">
                @if ($isSystem)
                    <p class="text-sm font-medium">{{ __('roles.name') }}</p>
                    <p class="mt-1 font-mono text-sm">{{ $role->name }}</p>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('roles.system_name_note') }}</p>
                @else
                    <x-admin.input name="name" :label="__('roles.name')" :value="$role?->name" :required="true"
                        :help="__('roles.name_help')" />
                @endif
            </div>
        </section>

        @unless ($isLocked)
            <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
                <h2 class="text-lg font-semibold">{{ __('roles.permissions') }}</h2>
                <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('roles.permissions_help') }}</p>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($modules as $module => $actions)
                        @php
                            $moduleLabel = \Illuminate\Support\Facades\Lang::has("roles.modules.{$module}")
                                ? __("roles.modules.{$module}")
                                : \Illuminate\Support\Str::headline($module);
                        @endphp
                        <fieldset class="rounded-xl border border-line p-4">
                            <legend class="px-2 text-sm font-semibold">{{ $moduleLabel }}</legend>
                            <button type="button"
                                class="mb-2 text-xs font-medium text-brand-700 hover:underline dark:text-brand-300"
                                x-on:click="$el.closest('fieldset').querySelectorAll('input[type=checkbox]:not(:disabled)').forEach((box) => box.checked = true)">
                                {{ __('roles.select_all') }}
                            </button>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($actions as $action)
                                    @php
                                        $permissionName = $module . '.' . $action;
                                        $actionLabel = \Illuminate\Support\Facades\Lang::has("roles.actions.{$action}")
                                            ? __("roles.actions.{$action}")
                                            : \Illuminate\Support\Str::headline($action);
                                    @endphp
                                    {{-- A permission the current user cannot grant is shown but disabled; it stays as it is. --}}
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permissionName }}"
                                            class="size-4 rounded border-line accent-brand-600" @checked(in_array($permissionName, $selectedPermissions, true))
                                            @disabled(!in_array($permissionName, $grantable, true))>
                                        {{ $actionLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @error('permissions')
                    <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                @error('permissions.*')
                    <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </section>
        @endunless

        <div class="flex items-center gap-3">
            @unless ($isSuperAdmin)
                <button type="submit" class="btn btn-primary"
                    x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            @endunless
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
