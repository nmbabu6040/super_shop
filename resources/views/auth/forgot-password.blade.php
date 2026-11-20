<x-layouts.auth :title="__('auth.forgot_title')">
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('auth.forgot_title') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('auth.forgot_subtitle') }}</p>
    </div>

    <x-auth.status class="mt-6" />

    <form method="POST" action="{{ route('password.email') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="mt-6 space-y-5" novalidate>
        @csrf

        <x-auth.field name="email" type="email" :label="__('auth.email_label')" autocomplete="email" :autofocus="true" />

        <x-auth.submit>{{ __('auth.send_reset_link') }}</x-auth.submit>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">
            {{ __('auth.back_to_login') }}
        </a>
    </p>
</x-layouts.auth>
