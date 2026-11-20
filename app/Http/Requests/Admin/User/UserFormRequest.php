<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use App\Support\Localization;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and updating a staff member.
 */
abstract class UserFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizedInput = [];

        if (is_string($this->input('email'))) {
            $normalizedInput['email'] = Str::lower(trim($this->input('email')));
        }

        if (is_string($this->input('username')) && trim($this->input('username')) !== '') {
            $normalizedInput['username'] = Str::lower(trim($this->input('username')));
        }

        if (is_string($this->input('phone'))) {
            $normalizedInput['phone'] = PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone');
        }

        // Only someone who has all-branch access can hand it out.
        $normalizedInput['has_all_branch_access'] = $this->user()->has_all_branch_access
            ? $this->boolean('has_all_branch_access')
            : false;

        $this->merge($normalizedInput);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(?User $editedUser): array
    {
        $ignoredId = $editedUser?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            // Unique across soft-deleted accounts too.
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($ignoredId)],
            'username' => ['nullable', 'string', 'regex:/^[a-z0-9._-]{3,50}$/', Rule::unique('users', 'username')->ignore($ignoredId)],
            'phone' => [
                'nullable',
                'string',
                Rule::unique('users', 'phone')->ignore($ignoredId),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (PhoneNumber::normalize((string) $value) === null) {
                        $fail(__('auth.invalid_phone'));
                    }
                },
            ],
            'preferred_locale' => ['nullable', Rule::in(Localization::supportedLocaleCodes())],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branch_ids' => [Rule::requiredIf(fn (): bool => ! $this->boolean('has_all_branch_access')), 'nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'has_all_branch_access' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('users.fields.name'),
            'email' => __('users.fields.email'),
            'username' => __('users.fields.username'),
            'phone' => __('users.fields.phone'),
            'preferred_locale' => __('users.fields.preferred_locale'),
            'roles' => __('users.fields.roles'),
            'branch_ids' => __('users.fields.branches'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('users.invalid_username'),
        ];
    }
}
