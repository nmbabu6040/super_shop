<x-layouts.admin :title="__('settings.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('settings.title'), null]]">
    <x-admin.page-header :title="__('settings.title')" />

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('settings.title') }}">
        @foreach ($groups as $groupKey)
            <a href="{{ route('admin.settings.edit', $groupKey) }}" @class([
                'rounded-full px-4 py-2 text-sm font-medium transition',
                'bg-brand-600 text-white' => $groupKey === $group,
                'border border-line bg-surface-raised text-ink-muted hover:text-ink' =>
                    $groupKey !== $group,
            ])
                @if ($groupKey === $group) aria-current="page" @endif>
                {{ __('settings.groups.' . $groupKey) }}
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data"
        x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false" novalidate>
        @csrf
        @method('PUT')

        <fieldset @disabled(!$canUpdate) class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            @unless ($canUpdate)
                <p class="mb-4 text-sm text-ink-muted">{{ __('settings.read_only_note') }}</p>
            @endunless

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($fields as $key => $field)
                    @php
                        $label = __("settings.fields.{$group}.{$key}");
                        $helpKey = "settings.help.{$group}.{$key}";
                        $help = \Illuminate\Support\Facades\Lang::has($helpKey) ? __($helpKey) : null;
                        $currentValue = $values[$key]['value'];
                        $selectOptions = collect($field['options'])
                            ->mapWithKeys(
                                fn($option): array => [
                                    $option =>
                                        $field['option_labels'] === 'date'
                                            ? now()->translatedFormat($option)
                                            : __("settings.options.{$group}.{$key}.{$option}"),
                                ],
                            )
                            ->all();
                    @endphp

                    @if ($field['readonly'])
                        <x-admin.input :name="$key" :label="$label" :value="$currentValue" :readonly="true"
                            :help="__('settings.readonly_note')" />
                    @elseif ($field['input'] === 'select')
                        <x-admin.select :name="$key" :label="$label" :options="$selectOptions" :selected="$currentValue"
                            :required="true" :help="$help" />
                    @elseif ($field['input'] === 'checkbox')
                        <div class="sm:col-span-2">
                            <x-admin.checkbox :name="$key" :label="$label" :checked="(bool) $currentValue" />
                            @if ($help)
                                <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
                            @endif
                        </div>
                    @elseif ($field['input'] === 'color')
                        <x-admin.input :name="$key" type="color" :label="$label" :value="$currentValue"
                            :help="$help" class="h-11 p-1" />
                    @elseif ($field['input'] === 'file')
                        <x-admin.file-input :name="$key" :label="$label" :current-path="$currentValue" :accept="$key === 'favicon_path' ? 'image/png,image/x-icon' : 'image/png,image/jpeg,image/webp'"
                            :help="$help" :remove-label="__('settings.remove_file')" />
                    @elseif ($field['input'] === 'secret')
                        @if ($canManageSecrets)
                            <x-admin.secret-input :name="$key" :label="$label" :has-secret="$values[$key]['has_secret']"
                                :help="$help" :saved-hint="__('settings.secret_saved')" :clear-label="__('settings.clear_secret')" />
                        @endif
                    @else
                        <x-admin.input :name="$key" :type="$field['input']" :label="$label" :value="$currentValue"
                            :help="$help" :required="in_array('required', $field['rules'], true)" />
                    @endif
                @endforeach
            </div>
        </fieldset>

        @if ($canUpdate)
            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="btn btn-primary"
                    x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            </div>
        @endif
    </form>
</x-layouts.admin>
