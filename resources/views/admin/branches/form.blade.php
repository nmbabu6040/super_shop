@php
    $isEditing = $branch !== null;
    $pageTitle = $isEditing ? __('admin.branches.edit_title') : __('admin.branches.create_title');
    $statusOptions = collect($statuses)->mapWithKeys(fn($status): array => [$status->value => $status->label()])->all();
    $managerOptions = $managers->pluck('name', 'id')->all();
    $weekDays = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('admin.branches.title'), route('admin.branches.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    <form method="POST"
        action="{{ $isEditing ? route('admin.branches.update', $branch) : route('admin.branches.store') }}"
        enctype="multipart/form-data" x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false"
        class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('admin.branches.section_general') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name" :label="__('admin.branches.name')" :value="$branch?->name" :required="true" />
                <x-admin.input name="code" :label="__('admin.branches.code')" :value="$branch?->code" :required="!$isEditing" :readonly="$isEditing"
                    :help="__('admin.branches.code_help')" style="text-transform: uppercase" />
                <x-admin.select name="status" :label="__('admin.branches.status')" :options="$statusOptions" :selected="$branch?->status->value ?? 'active'"
                    :required="true" />
                <x-admin.select name="manager_id" :label="__('admin.branches.manager')" :options="$managerOptions" :selected="$branch?->manager_id"
                    :placeholder="__('admin.common.none')" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('admin.branches.section_contact') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="phone" type="tel" :label="__('admin.branches.phone')" :value="$branch?->phone" />
                <x-admin.input name="email" type="email" :label="__('admin.branches.email')" :value="$branch?->email" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('admin.branches.section_location') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-admin.input name="address_line" :label="__('admin.branches.address_line')" :value="$branch?->address_line" />
                </div>
                <x-admin.input name="city" :label="__('admin.branches.city')" :value="$branch?->city" />
                <x-admin.input name="district" :label="__('admin.branches.district')" :value="$branch?->district" />
                <x-admin.input name="postal_code" :label="__('admin.branches.postal_code')" :value="$branch?->postal_code" />
                <div class="grid grid-cols-2 gap-4">
                    <x-admin.input name="latitude" type="number" step="any" :label="__('admin.branches.latitude')"
                        :value="$branch?->latitude" />
                    <x-admin.input name="longitude" type="number" step="any" :label="__('admin.branches.longitude')"
                        :value="$branch?->longitude" />
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('admin.branches.section_finance') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="opening_date" type="date" :label="__('admin.branches.opening_date')" :value="$branch?->opening_date?->format('Y-m-d')" />
                <x-admin.input name="opening_balance" type="number" step="0.01" min="0" :label="__('admin.branches.opening_balance')"
                    :value="$branch?->opening_balance" />
                <x-admin.input name="tax_registration_number" :label="__('admin.branches.tax_registration_number')" :value="$branch?->tax_registration_number" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="text-lg font-semibold">{{ __('admin.branches.working_hours') }}</h2>
            <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('admin.branches.working_hours_help') }}</p>
            <div class="space-y-3">
                @foreach ($weekDays as $weekDay)
                    <div class="grid items-center gap-3 sm:grid-cols-[10rem_1fr_1fr]">
                        <span class="text-sm font-medium">{{ __('admin.branches.days.' . $weekDay) }}</span>
                        <div>
                            <label for="hours-{{ $weekDay }}-open"
                                class="sr-only">{{ __('admin.branches.open') }}</label>
                            <input id="hours-{{ $weekDay }}-open" type="time"
                                name="working_hours[{{ $weekDay }}][open]"
                                value="{{ old('working_hours.' . $weekDay . '.open', $branch?->working_hours[$weekDay]['open'] ?? '') }}"
                                class="w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                            @error('working_hours.' . $weekDay . '.open')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="hours-{{ $weekDay }}-close"
                                class="sr-only">{{ __('admin.branches.close') }}</label>
                            <input id="hours-{{ $weekDay }}-close" type="time"
                                name="working_hours[{{ $weekDay }}][close]"
                                value="{{ old('working_hours.' . $weekDay . '.close', $branch?->working_hours[$weekDay]['close'] ?? '') }}"
                                class="w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                            @error('working_hours.' . $weekDay . '.close')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('admin.branches.logo') }}</h2>
            <div class="flex flex-wrap items-center gap-6">
                @if ($isEditing && $branch->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($branch->logo_path) }}"
                        alt="" class="size-20 rounded-xl border border-line object-cover">
                @endif
                <div class="space-y-3">
                    <div>
                        <label for="logo"
                            class="mb-1.5 block text-sm font-medium">{{ __('admin.branches.logo') }}</label>
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                            class="text-sm">
                        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.branches.logo_help') }}</p>
                        @error('logo')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    @if ($isEditing && $branch->logo_path)
                        <x-admin.checkbox name="remove_logo" :label="__('admin.branches.remove_logo')" :checked="false" />
                    @endif
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.branches.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
