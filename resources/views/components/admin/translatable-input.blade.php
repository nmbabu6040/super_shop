@props(['name', 'label', 'values' => null, 'required' => false, 'help' => null, 'multiline' => false])
@php
    $translations = $values instanceof \App\Support\Translations\TranslatedValue ? $values->toArray() : (array) $values;
    $inputClasses =
        'w-full rounded-xl border border-line bg-surface-raised px-3 py-2.5 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20 aria-[invalid=true]:border-red-500';
@endphp
<fieldset class="space-y-3 sm:col-span-2">
    <legend class="text-sm font-medium">
        {{ $label }}@if ($required)
            <span class="text-red-600" aria-hidden="true"> *</span>
        @endif
    </legend>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (config('locales.supported') as $localeCode => $localeMeta)
            @php($fieldId = $name . '-' . $localeCode)
            <div>
                <label for="{{ $fieldId }}"
                    class="mb-1 block text-xs text-ink-muted">{{ $localeMeta['native_name'] }}</label>
                @if ($multiline)
                    <textarea id="{{ $fieldId }}" name="{{ $name }}[{{ $localeCode }}]" rows="3"
                        aria-invalid="{{ $errors->has($name . '.' . $localeCode) ? 'true' : 'false' }}" class="{{ $inputClasses }}">{{ old($name . '.' . $localeCode, $translations[$localeCode] ?? '') }}</textarea>
                @else
                    <input id="{{ $fieldId }}" name="{{ $name }}[{{ $localeCode }}]" type="text"
                        value="{{ old($name . '.' . $localeCode, $translations[$localeCode] ?? '') }}"
                        @if ($required && $localeCode === 'en') required @endif
                        aria-invalid="{{ $errors->has($name . '.' . $localeCode) ? 'true' : 'false' }}"
                        class="{{ $inputClasses }}">
                @endif
                @error($name . '.' . $localeCode)
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
    @if ($help)
        <p class="text-xs text-ink-muted">{{ $help }}</p>
    @endif
</fieldset>
