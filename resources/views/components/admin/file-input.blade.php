@props([
    'name',
    'label',
    'currentPath' => null,
    'accept' => 'image/png,image/jpeg,image/webp',
    'help' => null,
    'removeLabel' => '',
])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @if ($currentPath)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentPath) }}" alt=""
            class="mb-2 size-16 rounded-lg border border-line object-contain">
    @endif
    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $accept }}" class="text-sm">
    @if ($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    @if ($currentPath)
        <label class="mt-2 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="remove_{{ $name }}" value="1"
                class="size-4 rounded border-line accent-brand-600">
            {{ $removeLabel }}
        </label>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
    @enderror
</div>
