@props(['name', 'label', 'checked' => false])
<div>
    {{-- The hidden "0" makes an unchecked box send an explicit false (and survives validation round-trips). --}}
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="{{ $name }}" value="1" class="size-4 rounded border-line accent-brand-600"
            @checked((bool) old($name, $checked))>
        {{ $label }}
    </label>
</div>
