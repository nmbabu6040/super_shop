<x-layouts.admin :title="__('audit.title')" :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('audit.title'), null]]">
    <x-admin.page-header :title="__('audit.title')">
        @if ($canExport)
            <a href="{{ route('admin.audit-logs.export', $filters) }}" class="btn btn-ghost">
                <x-ui.icon name="download" class="size-4" />
                {{ __('audit.export') }}
            </a>
        @endif
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="log_name" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('audit.log') }}</label>
            <select id="log_name" name="log_name"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($logNames as $logName)
                    <option value="{{ $logName }}" @selected(($filters['log_name'] ?? '') === $logName)>{{ $logName }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="search"
                class="mb-1 block text-xs font-medium text-ink-muted">{{ __('audit.description') }}</label>
            <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="causer" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('audit.user') }}</label>
            <input id="causer" name="causer" type="search" value="{{ $filters['causer'] ?? '' }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('audit.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('audit.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"
                class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    @error('to')
        <p class="mb-3 text-sm text-red-600" role="alert">{{ $message }}</p>
    @enderror

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('audit.time') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('audit.log') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('audit.description') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('audit.user') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('audit.subject') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('audit.details') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activities as $activity)
                    @php
                        $details = $activity->attribute_changes?->toArray() ?: $activity->properties?->toArray() ?: [];
                    @endphp
                    <tr class="border-t border-line align-top">
                        <td class="whitespace-nowrap px-4 py-3 text-ink-muted">
                            {{ $activity->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3"><x-admin.badge>{{ $activity->log_name }}</x-admin.badge></td>
                        <td class="px-4 py-3 font-medium">{{ $activity->description }}</td>
                        <td class="px-4 py-3">{{ $activity->causer?->name ?? __('audit.system') }}</td>
                        <td class="px-4 py-3">
                            @if ($activity->subject_type)
                                {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($details !== [])
                                <details>
                                    <summary class="cursor-pointer text-brand-700 dark:text-brand-300">
                                        {{ __('audit.show') }}</summary>
                                    <pre class="mt-2 max-w-md overflow-x-auto rounded-lg bg-surface p-2 text-xs">{{ json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-ink-muted">
                            {{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $activities->links() }}</div>
</x-layouts.admin>
