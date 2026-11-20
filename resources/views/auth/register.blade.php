<x-layouts.auth :title="__('auth.create_account_button')">
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('auth.register_title') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('auth.register_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="mt-6 space-y-5" novalidate>
        @csrf

        <x-auth.field name="name" :label="__('auth.name_label')" autocomplete="name" :autofocus="true" />
        <x-auth.field name="email" type="email" :label="__('auth.email_label')" autocomplete="email" />
        <x-auth.field name="phone" type="tel" :label="__('auth.phone_label')" autocomplete="tel" :required="false"
            :hint="__('auth.phone_hint')" />
        <x-auth.field name="password" type="password" :label="__('auth.password_label')" autocomplete="new-password" :hint="__('auth.password_hint')" />
        <x-auth.field name="password_confirmation" type="password" :label="__('auth.password_confirmation_label')" autocomplete="new-password" />

        <x-auth.submit>{{ __('auth.create_account_button') }}</x-auth.submit>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        {{ __('auth.have_account') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">
            {{ __('auth.sign_in') }}
        </a>
    </p>
</x-layouts.auth>
