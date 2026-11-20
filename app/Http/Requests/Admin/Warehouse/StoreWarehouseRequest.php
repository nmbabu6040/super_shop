<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Warehouse;

use App\Enums\WarehouseType;
use App\Models\Warehouse;
use Closure;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends WarehouseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Warehouse::class);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

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
            // Unique across soft-deleted warehouses too.
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{1,29}$/', Rule::unique('warehouses', 'code')],
            // Shop-floor warehouses exist only as the automatic default of a branch.
            'type' => [
                'required',
                Rule::in([WarehouseType::Storage->value, WarehouseType::Central->value]),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === WarehouseType::Central->value && ! $this->user()->has_all_branch_access) {
                        $fail(__('admin.warehouses.central_requires_head_office'));
                    }
                },
            ],
            'branch_id' => [
                'required_unless:type,' . WarehouseType::Central->value,
                'prohibited_if:type,' . WarehouseType::Central->value,
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && ! $this->user()->canAccessBranch((int) $value)) {
                        $fail(__('admin.warehouses.branch_not_accessible'));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.regex' => __('admin.warehouses.invalid_code'),
        ];
    }
}
