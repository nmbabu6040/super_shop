@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'readonly' => false,
])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)
            <span class="text-red-600" aria-hidden="true"> *</span>
        @endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @if ($required) required @endif @if ($readonly) readonly @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-xl border border-line bg-surface-raised px-3 py-2.5 text-sm text-ink outline-none transition placeholder:text-ink-muted focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20 read-only:bg-surface read-only:text-ink-muted aria-[invalid=true]:border-red-500']) }}>
    @if ($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
            {{ $message }}</p>
    @enderror
</div>
