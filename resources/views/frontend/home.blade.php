<x-layouts.app :title="__('ui.home')">
    <header class="border-b border-line bg-surface-raised/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('frontend.home') }}" class="text-lg font-bold text-brand-700 dark:text-brand-300">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-12">
        <section class="surface-card p-8 sm:p-12">
            <span
                class="inline-block rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800 dark:bg-brand-900 dark:text-brand-100">
                {{ __('ui.foundation_badge') }}
            </span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{{ __('ui.welcome_title') }}</h1>
            <p class="mt-3 max-w-2xl text-ink-muted">{{ __('ui.welcome_subtitle') }}</p>
            <div class="mt-6 flex gap-3">
                <a href="#tokens" class="btn btn-primary">{{ __('ui.tokens_title') }}</a>
            </div>
        </section>

        <section id="tokens" class="mt-10">
            <h2 class="text-xl font-semibold">{{ __('ui.tokens_title') }}</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="surface-card p-4">
                    <div class="h-12 rounded-lg bg-brand-600"></div>
                    <p class="mt-2 text-sm font-medium">{{ __('ui.token_brand') }}</p>
                </div>
                <div class="surface-card p-4">
                    <div class="h-12 rounded-lg bg-accent-500"></div>
                    <p class="mt-2 text-sm font-medium">{{ __('ui.token_accent') }}</p>
                </div>
                <div class="surface-card p-4">
                    <div class="h-12 rounded-lg border border-line bg-surface"></div>
                    <p class="mt-2 text-sm font-medium">{{ __('ui.token_surface') }}</p>
                </div>
                <div class="surface-card p-4">
                    <div class="h-12 rounded-lg bg-ink"></div>
                    <p class="mt-2 text-sm font-medium">{{ __('ui.token_ink') }}</p>
                </div>
            </div>
        </section>
    </main>
</x-layouts.app>
