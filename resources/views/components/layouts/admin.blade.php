@props(['title' => null, 'breadcrumbs' => []])
@php
    $currentUser = auth()->user();
    $siteName = app(\App\Services\Settings\SettingsService::class)->get('general', 'site_name', config('app.name'));
@endphp
<x-layouts.app :title="$title">
    <div x-data="{
        sidebarOpen: false,
        collapsed: false,
        init() {
            try { this.collapsed = localStorage.getItem('sidebar-collapsed') === '1'; } catch (storageError) {}
        },
        toggleCollapse() {
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0'); } catch (storageError) {}
        },
    }" class="min-h-screen lg:flex">

        {{-- Dark overlay behind the mobile sidebar --}}
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            x-on:click="sidebarOpen = false" aria-hidden="true"></div>

        <aside
            class="fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-surface-raised transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:transition-[width]"
            x-bind:class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64']"
            aria-label="{{ __('admin.topbar.main_navigation') }}">
            <div class="flex h-16 items-center justify-between border-b border-line px-4">
                <a href="{{ route('admin.dashboard') }}"
                    class="truncate text-lg font-bold text-brand-700 dark:text-brand-300">
                    <span x-bind:class="collapsed ? 'lg:hidden' : ''">{{ $siteName }}</span>
                    <span class="hidden"
                        x-bind:class="collapsed ? 'lg:inline' : ''">{{ mb_substr($siteName, 0, 1) }}</span>
                </a>
                <button type="button" class="lg:hidden" x-on:click="sidebarOpen = false"
                    aria-label="{{ __('admin.topbar.close_menu') }}">
                    <x-ui.icon name="x" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                @foreach (config('admin_menu') as $menuItem)
                    @if (empty($menuItem['can']) || $currentUser->can(...$menuItem['can']))
                        <a href="{{ route($menuItem['route']) }}" @class([
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-brand-600 text-white' => request()->routeIs($menuItem['active']),
                            'text-ink-muted hover:bg-surface hover:text-ink' => !request()->routeIs(
                                $menuItem['active']),
                        ])
                            @if (request()->routeIs($menuItem['active'])) aria-current="page" @endif>
                            <x-ui.icon :name="$menuItem['icon']" class="size-5 shrink-0" />
                            <span class="truncate"
                                x-bind:class="collapsed ? 'lg:hidden' : ''">{{ __($menuItem['label']) }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface-raised/90 px-4 backdrop-blur">
                <button type="button" class="lg:hidden" x-on:click="sidebarOpen = true"
                    aria-label="{{ __('admin.topbar.open_menu') }}">
                    <x-ui.icon name="menu" />
                </button>
                <button type="button" class="hidden lg:inline-flex" x-on:click="toggleCollapse()"
                    aria-label="{{ __('admin.topbar.collapse_menu') }}">
                    <x-ui.icon name="menu" />
                </button>

                @if ($breadcrumbs !== [])
                    <nav aria-label="Breadcrumb" class="hidden min-w-0 text-sm text-ink-muted sm:block">
                        <ol class="flex items-center gap-2">
                            @foreach ($breadcrumbs as [$crumbLabel, $crumbUrl])
                                <li class="flex items-center gap-2">
                                    @if ($crumbUrl)
                                        <a href="{{ $crumbUrl }}" class="hover:text-ink">{{ $crumbLabel }}</a>
                                    @else
                                        <span class="font-medium text-ink"
                                            aria-current="page">{{ $crumbLabel }}</span>
                                    @endif
                                    @unless ($loop->last)
                                        <span aria-hidden="true">/</span>
                                    @endunless
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                <div class="ms-auto flex items-center gap-2">
                    <x-admin.branch-switcher />
                    <x-admin.language-switcher />
                    <x-ui.theme-toggle />
                    <x-admin.notification-bell />

                    <div x-data="{ open: false }" x-on:click.outside="open = false"
                        x-on:keydown.escape.window="open = false" class="relative">
                        <button type="button" x-on:click="open = ! open"
                            class="flex items-center gap-2 rounded-full border border-line bg-surface-raised py-1 ps-1 pe-3 text-sm"
                            aria-haspopup="true" x-bind:aria-expanded="open">
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                                {{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}
                            </span>
                            <span class="hidden max-w-32 truncate sm:inline">{{ $currentUser->name }}</span>
                            <x-ui.icon name="chevron-down" class="size-4" />
                        </button>

                        <div x-show="open" x-cloak x-transition
                            class="absolute end-0 mt-2 w-60 rounded-xl border border-line bg-surface-raised p-2 shadow-card"
                            role="menu">
                            <p class="px-3 py-2 text-xs text-ink-muted">
                                {{ __('admin.topbar.signed_in_as') }}<br>
                                <span class="text-sm font-medium text-ink">{{ $currentUser->email }}</span>
                            </p>
                            <a href="{{ route('admin.notifications.index') }}"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface"
                                role="menuitem">
                                <x-ui.icon name="bell" class="size-4" />
                                {{ __('notifications.title') }}
                            </a>
                            <a href="{{ route('admin.notifications.preferences') }}"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface"
                                role="menuitem">
                                <x-ui.icon name="settings" class="size-4" />
                                {{ __('notifications.preferences_title') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface"
                                    role="menuitem">
                                    <x-ui.icon name="log-out" class="size-4" />
                                    {{ __('auth.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                <x-admin.flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
