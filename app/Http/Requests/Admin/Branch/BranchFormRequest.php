<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Branch;

use App\Enums\BranchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and updating a branch.
 */
abstract class BranchFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::enum(BranchStatus::class)],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active'),
            ],
            'opening_date' => ['nullable', 'date'],
            'opening_balance' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'tax_registration_number' => ['nullable', 'string', 'max:50'],
            'working_hours' => ['nullable', 'array:sat,sun,mon,tue,wed,thu,fri'],
            'working_hours.*' => ['array'],
            'working_hours.*.open' => ['nullable', 'date_format:H:i'],
            'working_hours.*.close' => ['nullable', 'date_format:H:i'],
            // SVG is not allowed: it can carry scripts.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('admin.branches.name'),
            'code' => __('admin.branches.code'),
            'status' => __('admin.branches.status'),
            'address_line' => __('admin.branches.address_line'),
            'city' => __('admin.branches.city'),
            'district' => __('admin.branches.district'),
            'postal_code' => __('admin.branches.postal_code'),
            'latitude' => __('admin.branches.latitude'),
            'longitude' => __('admin.branches.longitude'),
            'phone' => __('admin.branches.phone'),
            'email' => __('admin.branches.email'),
            'manager_id' => __('admin.branches.manager'),
            'opening_date' => __('admin.branches.opening_date'),
            'opening_balance' => __('admin.branches.opening_balance'),
            'tax_registration_number' => __('admin.branches.tax_registration_number'),
            'working_hours' => __('admin.branches.working_hours'),
            'logo' => __('admin.branches.logo'),
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
