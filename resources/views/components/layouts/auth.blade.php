@props(['title' => null])
@php($siteName = app(\App\Services\Settings\SettingsService::class)->get('general', 'site_name', config('app.name')))
<x-layouts.app :title="$title">
    <div class="auth-stage flex min-h-screen flex-col">
        {{-- Decorative layer: hidden from assistive technology and never intercepts clicks. --}}
        <div class="orb -start-24 top-10 size-72 bg-brand-400" aria-hidden="true"></div>
        <div class="orb -end-20 top-1/3 size-80 bg-accent-400" style="animation-delay: -4s" aria-hidden="true"></div>
        <div class="orb start-1/3 -bottom-24 size-72 bg-brand-600" style="animation-delay: -8s" aria-hidden="true"></div>

        <div class="scene-3d pointer-events-none absolute start-[8%] top-[18%] hidden lg:block" aria-hidden="true">
            <x-auth.cube />
        </div>
        <div class="scene-3d pointer-events-none absolute end-[9%] bottom-[16%] hidden scale-75 lg:block"
            aria-hidden="true">
            <x-auth.cube />
        </div>

        <header class="relative z-10 flex items-center justify-between px-6 py-5">
            <a href="{{ route('frontend.home') }}" class="text-lg font-bold text-brand-700 dark:text-brand-300">
                {{ $siteName }}
            </a>
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
            </div>
        </header>

        <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-8">
            <div x-data="tiltCard" x-on:mousemove="move($event)" x-on:mouseleave="reset()" x-on:focusin="freeze()"
                x-on:focusout="unfreeze()" x-bind:style="style"
                class="tilt-card glass-card w-full max-w-md p-8 sm:p-10">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.app>
