<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            ? $this->user()->can('update', $unit)
            : $this->user()->can('create', Unit::class);
    }

    protected function prepareForValidation(): void
    {

        $normalizedInput = [
            'is_decimal' => $this->boolean('is_decimal'),
        ];


        if (is_string($this->input('code'))) {
            $normalizedInput['code'] = trim($this->input('code'));
        }

        if ($this->has('is_active')) {
            $normalizedInput['is_active'] = $this->boolean('is_active');
        }

        $this->merge($normalizedInput);
    }



    /**
     * The code is only validated when creating: it never changes afterwards.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {



        $editedUnit = $this->route('unit');

        $codeRules = $editedUnit instanceof Unit
            ? []
            : [
                'code' => [
                    'required',
                    'string',
                    'regex:/^[a-z][a-z0-9_]{0,19}$/',
                    Rule::unique('units', 'code'),
                ],
            ];

        return [
            ...$codeRules,
            ...$this->translatableRules('name', true, 100),
            'symbol' => ['required', 'string', 'max:10'],
            'is_decimal' => ['boolean'],
            'decimal_places' => ['nullable', 'integer', 'between:0,6'],
            // Only a base unit (one that is not itself derived) can be chosen.
            'base_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->whereNull('base_unit_id'),
                Rule::notIn(
                    $editedUnit instanceof Unit ? [$editedUnit->getKey()] : []
                ),
            ],
            'conversion_factor' => ['nullable', 'numeric', 'decimal:0,6', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->translatableAttributes('name', __('catalog.name')),
            'code' => __('catalog.units.code'),
            'symbol' => __('catalog.units.symbol'),
            'decimal_places' => __('catalog.units.decimal_places'),
            'base_unit_id' => __('catalog.units.base_unit'),
            'conversion_factor' => __('catalog.units.conversion_factor'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('catalog.units.invalid_code'),
        ];
    }
}
