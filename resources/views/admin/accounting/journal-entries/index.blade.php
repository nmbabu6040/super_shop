<x-layouts.admin :title="__('journal.title')"
                 :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('journal.title'), null]]">
    <x-admin.page-header :title="__('journal.title')" :subtitle="__('journal.subtitle')">
        @can('create', \App\Models\JournalEntry::class)
            <a href="{{ route('admin.journal-entries.create') }}" class="btn btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('journal.create_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('admin.common.search') }}</label>
            <input id="search" name="search" type="search" value="{{ $searchTerm }}"
                   placeholder="{{ __('journal.search_placeholder') }}"
                   class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.fields.status') }}</label>
            <select id="status" name="status" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="">{{ __('admin.common.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.from') }}</label>
            <input id="from" name="from" type="date" value="{{ $fromDate }}" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.ledger.to') }}</label>
            <input id="to" name="to" type="date" value="{{ $toDate }}" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
        </div>
        <div>
            <label for="branch_id" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('journal.fields.branch') }}</label>
            <select id="branch_id" name="branch_id" class="rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm">
                <option value="0">{{ __('admin.common.all') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchFilter === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <x-ui.icon name="search" class="size-4" />
            {{ __('admin.common.filter') }}
        </button>
        <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-ghost">{{ __('admin.common.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-card border border-line bg-surface-raised shadow-card">
        <table class="min-w-full text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.number') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.date') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.description') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.branch') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('journal.fields.total') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('journal.fields.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('admin.journal-entries.show', $entry) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ $entry->entry_number }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $entry->entry_date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $entry->description }}</td>
                        <td class="px-4 py-3">{{ $entry->branch?->name ?? __('journal.head_office') }}</td>
                        <td class="px-4 py-3 text-end">{{ \App\Support\Money\MoneyFormatter::format($entry->total_amount) }}</td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="match ($entry->status) {
                                \App\Enums\JournalEntryStatus::Posted => 'success',
                                \App\Enums\JournalEntryStatus::Pending => 'warning',
                                \App\Enums\JournalEntryStatus::Rejected => 'danger',
                            }">{{ $entry->status->label() }}</x-admin.badge>
                            @if ($entry->isReversed())
                                <x-admin.badge tone="neutral" class="ms-1">{{ __('journal.reversed_badge') }}</x-admin.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-ink-muted">{{ __('admin.common.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.admin>
