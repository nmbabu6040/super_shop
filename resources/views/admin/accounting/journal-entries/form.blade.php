@php
    $oldLines = old('lines');
    $initialLines =
        is_array($oldLines) && $oldLines !== []
            ? collect($oldLines)
                ->values()
                ->map(
                    fn(array $line): array => [
                        'account_id' => (string) ($line['account_id'] ?? ''),
                        'debit' => (string) ($line['debit'] ?? ''),
                        'credit' => (string) ($line['credit'] ?? ''),
                        'memo' => (string) ($line['memo'] ?? ''),
                    ],
                )
                ->all()
            : [
                ['account_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''],
                ['account_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''],
            ];
    $fieldClasses =
        'w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20';
@endphp
<x-layouts.admin :title="__('journal.create_title')" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('journal.title'), route('admin.journal-entries.index')],
    [__('journal.create_title'), null],
]">
    <x-admin.page-header :title="__('journal.create_title')" :subtitle="__('journal.pending_note')" />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100"
            role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $errorMessage)
                    <li>{{ $errorMessage }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.journal-entries.store') }}" x-data="{
        busy: false,
        lines: @js($initialLines),
        addLine() { if (this.lines.length < 100) { this.lines.push({ account_id: '', debit: '', credit: '', memo: '' }); } },
        removeLine(index) { if (this.lines.length > 2) { this.lines.splice(index, 1); } },
        cents(value) { return Math.round(parseFloat(value || 0) * 100) || 0; },
        totalDebit() { return this.lines.reduce((sum, line) => sum + this.cents(line.debit), 0); },
        totalCredit() { return this.lines.reduce((sum, line) => sum + this.cents(line.credit), 0); },
        difference() { return this.totalDebit() - this.totalCredit(); },
        format(cents) { return (cents / 100).toFixed(2); },
    }"
        x-on:submit="busy = true" x-on:pageshow.window="busy = false" class="space-y-6" novalidate>
        @csrf

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="entry_date" type="date" :label="__('journal.fields.date')" :value="old('entry_date', $defaultDate)" :required="true" />
                <div class="sm:col-span-2">
                    <x-admin.input name="description" :label="__('journal.fields.description')" :required="true" />
                </div>
                <div>
                    <label for="branch_id"
                        class="mb-1.5 block text-sm font-medium">{{ __('journal.fields.branch') }}</label>
                    <select id="branch_id" name="branch_id" class="{{ $fieldClasses }}">
                        @if ($canUseHeadOffice)
                            <option value="">{{ __('journal.head_office') }}</option>
                        @endif
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $defaultBranchId) === (string) $branch->id)>{{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('journal.fields.lines') }}</h2>

            <div class="space-y-3">
                <template x-for="(line, index) in lines" x-bind:key="index">
                    <div class="grid gap-2 rounded-xl border border-line p-3 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <select class="{{ $fieldClasses }}" x-model="line.account_id"
                                x-bind:name="`lines[${index}][account_id]`">
                                <option value="">{{ __('journal.fields.account') }}</option>
                                @foreach ($accountOptions as $accountId => $accountLabel)
                                    <option value="{{ $accountId }}">{{ $accountLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <input type="text" inputmode="decimal" class="{{ $fieldClasses }}"
                                placeholder="{{ __('journal.fields.debit') }}" x-model="line.debit"
                                x-bind:name="`lines[${index}][debit]`">
                        </div>
                        <div class="sm:col-span-2">
                            <input type="text" inputmode="decimal" class="{{ $fieldClasses }}"
                                placeholder="{{ __('journal.fields.credit') }}" x-model="line.credit"
                                x-bind:name="`lines[${index}][credit]`">
                        </div>
                        <div class="sm:col-span-3">
                            <input type="text" class="{{ $fieldClasses }}"
                                placeholder="{{ __('journal.fields.memo') }}" x-model="line.memo"
                                x-bind:name="`lines[${index}][memo]`">
                        </div>
                        <div class="flex items-center justify-end sm:col-span-1">
                            <button type="button" class="btn btn-ghost" x-on:click="removeLine(index)"
                                x-bind:disabled="lines.length <= 2" aria-label="{{ __('journal.remove_line') }}">
                                <x-ui.icon name="x" class="size-4" />
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" class="btn btn-ghost mt-4" x-on:click="addLine()">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('journal.add_line') }}
            </button>

            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-ink-muted">{{ __('journal.fields.debit') }}</dt>
                    <dd class="font-semibold" x-text="format(totalDebit())"></dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('journal.fields.credit') }}</dt>
                    <dd class="font-semibold" x-text="format(totalCredit())"></dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('journal.difference') }}</dt>
                    <dd class="font-semibold" x-bind:class="difference() === 0 ? 'text-emerald-600' : 'text-red-600'"
                        x-text="format(Math.abs(difference()))"></dd>
                </div>
                <div class="flex items-end">
                    <span class="text-xs" x-show="difference() === 0 && totalDebit() > 0"
                        x-cloak>{{ __('journal.balanced') }}</span>
                    <span class="text-xs text-red-600" x-show="difference() !== 0"
                        x-cloak>{{ __('journal.not_balanced') }}</span>
                </div>
            </dl>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('journal.submit_pending') }}</button>
            <a href="{{ route('admin.journal-entries.index') }}"
                class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
