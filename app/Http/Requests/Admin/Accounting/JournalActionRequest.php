<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason typed when rejecting or reversing an entry (and the optional reversal date).
 * Authorization is checked by the controller through the policy.
 */
class JournalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:200'],
            'reversal_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('journal.fields.reason'),
            'reversal_date' => __('journal.fields.reversal_date'),
        ];
    }
}
