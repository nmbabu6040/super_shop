@if (session('status'))
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-700 dark:bg-brand-900/40 dark:text-brand-100']) }}
        role="status">
        {{ session('status') }}
    </div>
@endif
