@props(['name', 'label', 'hasSecret' => false, 'help' => null, 'savedHint' => '', 'clearLabel' => ''])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    {{-- The stored secret is never sent to the browser: a blank field keeps it unchanged. --}}
    <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="new-password"
        placeholder="{{ $hasSecret ? '••••••••' : '' }}"
        class="w-full rounded-xl border border-line bg-surface-raised px-3 py-2.5 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20">
    @if ($hasSecret)
        <p class="mt-1 text-xs text-ink-muted">{{ $savedHint }}</p>
        <label class="mt-2 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="clear_{{ $name }}" value="1"
                class="size-4 rounded border-line accent-brand-600">
            {{ $clearLabel }}
        </label>
    @endif
    @if ($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
    @enderror
</div>
