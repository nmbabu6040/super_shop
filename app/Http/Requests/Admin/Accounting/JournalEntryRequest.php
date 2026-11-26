<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use App\Support\Branch\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryRequest extends FormRequest
{
    private const AMOUNT_PATTERN = '/^\d{1,12}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return $this->user()->can('create', JournalEntry::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'lines.*.debit' => ['nullable', 'regex:' . self::AMOUNT_PATTERN],
            'lines.*.credit' => ['nullable', 'regex:' . self::AMOUNT_PATTERN],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Rules that compare several fields or look at the database; they run only when the
     * single-field rules passed. The journal service checks everything again when posting.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateLines($validator);
            $this->validateBranch($validator);
        });
    }

    private function validateLines(Validator $validator): void
    {
        $lines = array_values((array) $this->input('lines'));
        $accounts = Account::query()
            ->whereIn('id', array_map(static fn (array $line): int => (int) $line['account_id'], $lines))
            ->get()
            ->keyBy('id');

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $index => $line) {
            $debit = $this->amountOrZero($line['debit'] ?? null);
            $credit = $this->amountOrZero($line['credit'] ?? null);
            $hasDebit = bccomp($debit, '0', 2) === 1;
            $hasCredit = bccomp($credit, '0', 2) === 1;

            if ($hasDebit === $hasCredit) {
                $validator->errors()->add("lines.{$index}.debit", __('accounting.errors.line_needs_one_side'));
            }

            $account = $accounts->get((int) $line['account_id']);

            if ($account !== null && ! $account->is_active) {
                $validator->errors()->add("lines.{$index}.account_id", __('accounting.errors.account_inactive', ['code' => $account->code]));
            } elseif ($account !== null && ! $account->is_postable) {
                $validator->errors()->add("lines.{$index}.account_id", __('accounting.errors.account_not_postable', ['code' => $account->code]));
            }

            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);
        }

        if ($validator->errors()->isEmpty() && bccomp($totalDebit, $totalCredit, 2) !== 0) {
            $validator->errors()->add('lines', __('accounting.errors.unbalanced', ['debit' => $totalDebit, 'credit' => $totalCredit]));
        }
    }

    private function validateBranch(Validator $validator): void
    {
        if (! $this->filled('branch_id')) {
            return;
        }

        $accessibleBranchIds = $this->user()->accessibleBranchesQuery()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if (! in_array((int) $this->input('branch_id'), $accessibleBranchIds, true)) {
            $validator->errors()->add('branch_id', __('journal.errors.invalid_branch'));
        }
    }

    /**
     * Turns the validated form into a draft for the journal service. Branch-bound staff
     * always post into their working branch; head office users may choose "no branch".
     */
    public function toDraft(): JournalDraft
    {
        $branchContext = app(BranchContext::class);
        $branchId = $this->filled('branch_id') ? (int) $this->input('branch_id') : null;

        if ($branchId === null && $branchContext->restrictedBranchIds() !== null) {
            $branchId = $branchContext->activeBranchId();
        }

        $lines = array_map(
            fn (array $line): JournalLineDraft => new JournalLineDraft(
                (int) $line['account_id'],
                $this->amountOrZero($line['debit'] ?? null),
                $this->amountOrZero($line['credit'] ?? null),
                isset($line['memo']) && $line['memo'] !== '' ? (string) $line['memo'] : null,
            ),
            array_values((array) $this->validated('lines'))
        );

        return new JournalDraft(
            CarbonImmutable::parse((string) $this->validated('entry_date')),
            (string) $this->validated('description'),
            $lines,
            $branchId,
        );
    }

    private function amountOrZero(mixed $value): string
    {
        return $value === null || $value === '' ? '0.00' : bcadd((string) $value, '0', 2);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entry_date' => __('journal.fields.date'),
            'description' => __('journal.fields.description'),
            'branch_id' => __('journal.fields.branch'),
            'lines' => __('journal.fields.lines'),
            'lines.*.account_id' => __('journal.fields.account'),
            'lines.*.debit' => __('journal.fields.debit'),
            'lines.*.credit' => __('journal.fields.credit'),
            'lines.*.memo' => __('journal.fields.memo'),
        ];
    }
}
