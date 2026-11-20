<form method="POST" action="{{ route('admin.locale.update') }}"
    class="inline-flex rounded-full border border-line bg-surface-raised p-0.5 text-sm" role="group"
    aria-label="{{ __('admin.topbar.language') }}">
    @csrf
    @foreach (config('locales.supported') as $localeCode => $localeMeta)
        <button type="submit" name="locale" value="{{ $localeCode }}" lang="{{ $localeCode }}"
            @class([
                'rounded-full px-3 py-1 font-medium transition',
                'bg-brand-600 text-white' => $localeCode === app()->getLocale(),
                'text-ink-muted hover:text-ink' => $localeCode !== app()->getLocale(),
            ]) @if ($localeCode === app()->getLocale()) aria-current="true" @endif>
            {{ $localeMeta['native_name'] }}
        </button>
    @endforeach
</form>
