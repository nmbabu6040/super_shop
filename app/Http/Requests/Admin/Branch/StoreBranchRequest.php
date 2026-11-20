<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Branch;

use App\Models\Branch;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends BranchFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Branch::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            // Unique across soft-deleted branches too, so a code is never reused.
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{1,19}$/', Rule::unique('branches', 'code')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.regex' => __('admin.branches.invalid_code'),
        ];
    }
}
