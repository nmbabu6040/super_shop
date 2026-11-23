<x-layouts.admin :title="__('notifications.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('notifications.title'), null]]">
    <x-admin.page-header :title="__('notifications.title')">
        <a href="{{ route('admin.notifications.preferences') }}" class="btn btn-ghost">
            <x-ui.icon name="settings" class="size-4" />
            {{ __('notifications.preferences_title') }}
        </a>
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('notifications.mark_all_read') }}</button>
        </form>
    </x-admin.page-header>

    <div class="overflow-hidden rounded-card border border-line bg-surface-raised shadow-card">
        <ul class="divide-y divide-line">
            @forelse ($notifications as $notification)
                @php($item = $presenter->present($notification))
                <li class="flex items-start justify-between gap-4 px-5 py-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span @class([
                            'mt-2 size-2 shrink-0 rounded-full',
                            'bg-brand-600' => !$item['is_read'],
                            'bg-transparent' => $item['is_read'],
                        ])></span>
                        <div class="min-w-0">
                            <p @class(['text-sm', 'font-semibold' => !$item['is_read']])>{{ $item['title'] }}</p>
                            <p class="text-sm text-ink-muted">{{ $item['message'] }}</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ $item['time'] }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if (!$item['is_read'])
                            <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost">
                                    {{ $item['url'] ? __('notifications.open') : __('notifications.mark_read') }}
                                </button>
                            </form>
                        @elseif ($item['url'])
                            <a href="{{ $item['url'] }}" class="btn btn-ghost">{{ __('notifications.open') }}</a>
                        @endif
                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost text-red-600"
                                aria-label="{{ __('admin.common.delete') }}">
                                <x-ui.icon name="trash" class="size-4" />
                            </button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-ink-muted">{{ __('notifications.empty') }}</li>
            @endforelse
        </ul>
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.admin>
