@php
    $flashMessages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'status' => session('status'),
    ]);
@endphp
@foreach ($flashMessages as $flashType => $flashMessage)
    <div x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 7000)" x-show="visible" x-transition
        role="{{ $flashType === 'error' ? 'alert' : 'status' }}" @class([
            'mb-4 flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm',
            'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-100' =>
                $flashType !== 'error',
            'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100' =>
                $flashType === 'error',
        ])>
        <span>{{ $flashMessage }}</span>
        <button type="button" x-on:click="visible = false" aria-label="{{ __('admin.topbar.close_menu') }}">
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>
@endforeach
