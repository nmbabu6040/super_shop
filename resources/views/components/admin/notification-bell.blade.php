<div x-data="notificationBell({
    feedUrl: @js(route('admin.notifications.feed')),
    readUrlTemplate: @js(route('admin.notifications.read', ['notification' => '__ID__'])),
    readAllUrl: @js(route('admin.notifications.read-all')),
    unread: {{ $unreadCount }},
})" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" class="relative">
    <button type="button" x-on:click="toggle()" class="btn btn-ghost relative size-9 !p-0" aria-haspopup="true"
        x-bind:aria-expanded="open" aria-label="{{ __('notifications.bell_label') }}">
        <x-ui.icon name="bell" />
        <span x-show="unread > 0" x-cloak
            class="absolute -end-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-5 text-white"
            x-text="unread > 99 ? '99+' : unread" role="status" aria-live="polite"></span>
    </button>

    <div x-show="open" x-cloak x-transition
        class="absolute end-0 z-30 mt-2 w-80 rounded-xl border border-line bg-surface-raised shadow-card"
        role="menu">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <span class="text-sm font-semibold">{{ __('notifications.title') }}</span>
            <button type="button" x-show="unread > 0" x-on:click="markAllRead()"
                class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">
                {{ __('notifications.mark_all_read') }}
            </button>
        </div>

        <ul class="max-h-96 divide-y divide-line overflow-y-auto">
            <template x-for="item in items" x-bind:key="item.id">
                <li>
                    <button type="button" x-on:click="openItem(item)"
                        class="block w-full px-4 py-3 text-start hover:bg-surface" role="menuitem">
                        <span class="flex items-start gap-2">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full"
                                x-bind:class="item.is_read ? 'bg-transparent' : 'bg-brand-600'"></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium" x-text="item.title"></span>
                                <span class="block text-xs text-ink-muted" x-text="item.message"></span>
                                <span class="mt-1 block text-[11px] text-ink-muted" x-text="item.time"></span>
                            </span>
                        </span>
                    </button>
                </li>
            </template>
            <li x-show="items.length === 0 && ! loading" class="px-4 py-6 text-center text-sm text-ink-muted">
                {{ __('notifications.empty') }}
            </li>
        </ul>

        <a href="{{ route('admin.notifications.index') }}"
            class="block border-t border-line px-4 py-2.5 text-center text-sm font-medium text-brand-700 hover:bg-surface dark:text-brand-300">
            {{ __('notifications.view_all') }}
        </a>
    </div>
</div>
