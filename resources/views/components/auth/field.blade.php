@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'hint' => null,
    'required' => true,
    'autofocus' => false,
])
@php
    $errorId = $name . '-error';
    $hintId = $name . '-hint';
    $describedBy = trim(($errors->has($name) ? $errorId : '') . ' ' . ($hint ? $hintId : ''));
@endphp
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>

    @if ($type === 'password')
        {{-- Passwords are never put back into the form after a failed submit. --}}
        <div x-data="{ visible: false }" class="relative">
            <input id="{{ $name }}" name="{{ $name }}" type="password"
                x-bind:type="visible ? 'text' : 'password'" class="auth-input pe-12"
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                @if ($required) required @endif @if ($autofocus) autofocus @endif
                aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
            <button type="button" x-on:click="visible = ! visible"
                class="absolute inset-y-0 end-0 flex items-center px-3 text-ink-muted hover:text-ink"
                aria-label="{{ __('auth.toggle_password') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
            </button>
        </div>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            value="{{ $value ?? old($name) }}" class="auth-input"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($required) required @endif @if ($autofocus) autofocus @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    @endif

    @if ($hint)
        <p id="{{ $hintId }}" class="mt-1.5 text-xs text-ink-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">
            {{ $message }}</p>
    @enderror
</div>
