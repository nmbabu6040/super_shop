<x-layouts.auth :title="__('auth.reset_title')">
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('auth.reset_title') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('auth.reset_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" x-data="{ busy: false }" x-on:submit="busy = true"
        x-on:pageshow.window="busy = false" class="mt-6 space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-auth.field name="email" type="email" :label="__('auth.email_label')" :value="$email" autocomplete="email" />
        <x-auth.field name="password" type="password" :label="__('auth.new_password_label')" autocomplete="new-password" :hint="__('auth.password_hint')"
            :autofocus="true" />
        <x-auth.field name="password_confirmation" type="password" :label="__('auth.password_confirmation_label')" autocomplete="new-password" />

        <x-auth.submit>{{ __('auth.reset_password_button') }}</x-auth.submit>
    </form>
</x-layouts.auth>
