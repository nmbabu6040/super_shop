@props(['name', 'label', 'options', 'selected' => null, 'placeholder' => null, 'required' => false, 'help' => null])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)
            <span class="text-red-600" aria-hidden="true"> *</span>
        @endif
    </label>
    <select id="{{ $name }}" name="{{ $name }}" @if ($required) required @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-xl border border-line bg-surface-raised px-3 py-2.5 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20 aria-[invalid=true]:border-red-500']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    @if ($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
            {{ $message }}</p>
    @enderror
</div>
