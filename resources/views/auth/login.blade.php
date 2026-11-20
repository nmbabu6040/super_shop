<x-layouts.auth :title="__('auth.sign_in')">
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('auth.login_title') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('auth.login_subtitle') }}</p>
    </div>

    <x-auth.status class="mt-6" />

    <form method="POST" action="{{ route('login.attempt') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="mt-6 space-y-5" novalidate>
        @csrf

        <x-auth.field name="identifier" :label="__('auth.identifier_label')" autocomplete="username" :autofocus="true" />
        <x-auth.field name="password" type="password" :label="__('auth.password_label')" autocomplete="current-password" />

        <div class="flex items-center justify-between text-sm">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="remember" value="1"
                    class="size-4 rounded border-line accent-brand-600" @checked(old('remember'))>
                {{ __('auth.remember_me') }}
            </label>
            <a href="{{ route('password.request') }}"
                class="font-medium text-brand-700 hover:underline dark:text-brand-300">
                {{ __('auth.forgot_password') }}
            </a>
        </div>

        <x-auth.submit>{{ __('auth.sign_in') }}</x-auth.submit>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        {{ __('auth.no_account') }}
        <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">
            {{ __('auth.create_account') }}
        </a>
    </p>
</x-layouts.auth>
