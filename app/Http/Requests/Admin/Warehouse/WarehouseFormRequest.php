<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and updating a warehouse.
 */
abstract class WarehouseFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_sellable' => $this->boolean('is_sellable'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'is_sellable' => ['boolean'],
            'is_active' => ['boolean'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('admin.warehouses.name'),
            'code' => __('admin.warehouses.code'),
            'type' => __('admin.warehouses.type'),
            'branch_id' => __('admin.warehouses.branch'),
            'address_line' => __('admin.warehouses.address_line'),
            'phone' => __('admin.warehouses.phone'),
            'manager_id' => __('admin.warehouses.manager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('admin.branches.invalid_phone'),
        ];
    }
}
