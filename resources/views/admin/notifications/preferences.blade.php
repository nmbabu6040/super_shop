<x-layouts.admin :title="__('notifications.preferences_title')" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('notifications.title'), route('admin.notifications.index')],
    [__('notifications.preferences_title'), null],
]">
    <x-admin.page-header :title="__('notifications.preferences_title')" :subtitle="__('notifications.preferences_help')" />

    <form method="POST" action="{{ route('admin.notifications.preferences.update') }}" x-data="{ busy: false }"
        x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
            <table class="min-w-full text-sm">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('notifications.type') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('notifications.channel_email') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('notifications.channel_in_app') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($state as $type => $channels)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ __("notifications.types.{$type}.label") }}</p>
                                <p class="text-xs text-ink-muted">{{ __("notifications.types.{$type}.description") }}
                                </p>
                            </td>
                            @foreach (['email', 'in_app'] as $channelField)
                                <td class="px-4 py-3 text-center">
                                    @if ($channels[$channelField]['available'])
                                        {{-- Hidden "0" makes an unchecked box send an explicit false. --}}
                                        @unless ($channels[$channelField]['locked'])
                                            <input type="hidden"
                                                name="preferences[{{ $type }}][{{ $channelField }}]" value="0">
                                        @endunless
                                        <input type="checkbox"
                                            name="preferences[{{ $type }}][{{ $channelField }}]" value="1"
                                            class="size-4 rounded border-line accent-brand-600"
                                            @checked($channels[$channelField]['enabled']) @disabled($channels[$channelField]['locked'])>
                                        @if ($channels[$channelField]['locked'])
                                            <span
                                                class="block text-[11px] text-ink-muted">{{ __('notifications.locked') }}</span>
                                        @endif
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
        </div>
    </form>
</x-layouts.admin>
