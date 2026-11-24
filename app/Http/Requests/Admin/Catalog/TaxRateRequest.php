<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\TaxRate;
use Illuminate\Foundation\Http\FormRequest;

class TaxRateRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $taxRate = $this->route('tax_rate');

        return $taxRate instanceof TaxRate
            ? $this->user()->can('update', $taxRate)
            : $this->user()->can('create', TaxRate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->translatableRules('name', true, 100),
            'rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->translatableAttributes('name', __('catalog.name')),
            'rate' => __('tax.rate'),
        ];
    }
}
