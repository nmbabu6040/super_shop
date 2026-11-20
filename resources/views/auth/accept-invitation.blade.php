<x-layouts.auth :title="__('invitation.title')">
    @if ($invitedUser === null)
        <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight">{{ __('invitation.title') }}</h1>
            <p class="mt-3 text-sm text-ink-muted" role="alert">{{ __('invitation.invalid') }}</p>
            <a href="{{ route('login') }}"
                class="mt-6 inline-block text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
                {{ __('auth.back_to_login') }}
            </a>
        </div>
    @else
        <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight">{{ __('invitation.title') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ __('invitation.subtitle', ['name' => $invitedUser->name]) }}</p>
            <p class="mt-1 text-sm font-medium">{{ $invitedUser->email }}</p>
        </div>

        <form method="POST" action="{{ route('invitation.store') }}" x-data="{ busy: false }" x-on:submit="busy = true"
            x-on:pageshow.window="busy = false" class="mt-6 space-y-5" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <x-auth.field name="password" type="password" :label="__('auth.new_password_label')" autocomplete="new-password"
                :hint="__('auth.password_hint')" :autofocus="true" />
            <x-auth.field name="password_confirmation" type="password" :label="__('auth.password_confirmation_label')" autocomplete="new-password" />

            <x-auth.submit>{{ __('invitation.submit') }}</x-auth.submit>
        </form>
    @endif
</x-layouts.auth>
