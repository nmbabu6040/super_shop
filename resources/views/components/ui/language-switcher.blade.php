<div class="inline-flex rounded-full border border-line bg-surface-raised p-0.5 text-sm" role="group"
    aria-label="{{ __('ui.language') }}">
    @foreach (config('locales.supported') as $localeCode => $localeMeta)
        <a href="{{ \App\Support\Localization::switchUrl($localeCode, request()) }}" hreflang="{{ $localeCode }}"
            lang="{{ $localeCode }}" @class([
                'rounded-full px-3 py-1 font-medium transition',
                'bg-brand-600 text-white' => $localeCode === app()->getLocale(),
                'text-ink-muted hover:text-ink' => $localeCode !== app()->getLocale(),
            ])
            @if ($localeCode === app()->getLocale()) aria-current="true" @endif>
            {{ $localeMeta['native_name'] }}
        </a>
    @endforeach
</div>
