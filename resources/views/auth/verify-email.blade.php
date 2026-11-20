<x-layouts.auth :title="__('auth.verify_title')">
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('auth.verify_title') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('auth.verify_subtitle') }}</p>
    </div>

    <x-auth.status class="mt-6" />

    <form method="POST" action="{{ route('verification.send') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="mt-6">
        @csrf
        <x-auth.submit>{{ __('auth.resend_verification') }}</x-auth.submit>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
            {{ __('auth.logout') }}
        </button>
    </form>
</x-layouts.auth>
