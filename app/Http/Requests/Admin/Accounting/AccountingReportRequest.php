<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Models\JournalEntry;
use App\Support\Branch\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the trial balance and the general ledger.
 */
class AccountingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewReports', JournalEntry::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('from') && $this->filled('to') && $this->input('to') < $this->input('from')) {
                $validator->errors()->add('to', __('journal.errors.range'));
            }

            if ($this->filled('branch_id')) {
                $accessibleBranchIds = $this->user()->accessibleBranchesQuery()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

                if (! in_array((int) $this->input('branch_id'), $accessibleBranchIds, true)) {
                    $validator->errors()->add('branch_id', __('journal.errors.invalid_branch'));
                }
            }
        });
    }

    public function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->filled('as_of') ? (string) $this->input('as_of') : now()->format('Y-m-d'));
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->filled('from') ? (string) $this->input('from') : now()->startOfMonth()->format('Y-m-d'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->filled('to') ? (string) $this->input('to') : now()->format('Y-m-d'));
    }

    /**
     * The branches the report covers: the chosen one, else everything the user may see
     * (null = no restriction for head office users).
     *
     * @return list<int>|null
     */
    public function branchIds(): ?array
    {
        if ($this->filled('branch_id')) {
            return [(int) $this->input('branch_id')];
        }

        return app(BranchContext::class)->restrictedBranchIds();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'as_of' => __('journal.trial.as_of'),
            'from' => __('journal.ledger.from'),
            'to' => __('journal.ledger.to'),
            'branch_id' => __('journal.fields.branch'),
            'account_id' => __('journal.fields.account'),
        ];
    }
}
