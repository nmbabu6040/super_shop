<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Models\FiscalYear;
use Illuminate\Foundation\Http\FormRequest;

class FiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FiscalYear::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('accounting.fiscal.name'),
            'starts_on' => __('accounting.fiscal.starts_on'),
        ];
    }
}
