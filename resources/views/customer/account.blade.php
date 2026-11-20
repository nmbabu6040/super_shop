<x-layouts.app :title="__('auth.account_title')">
    <header class="border-b border-line bg-surface-raised/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('frontend.home') }}" class="text-lg font-bold text-brand-700 dark:text-brand-300">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">{{ __('auth.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-12">
        <x-auth.status class="mb-6" />

        <section class="surface-card p-8">
            <h1 class="text-2xl font-bold">{{ __('auth.account_welcome', ['name' => $user->name]) }}</h1>
            <p class="mt-2 text-ink-muted">{{ $user->email }}</p>
            <p class="mt-6 text-sm text-ink-muted">{{ __('auth.account_coming_soon') }}</p>
        </section>
    </main>
</x-layouts.app>
