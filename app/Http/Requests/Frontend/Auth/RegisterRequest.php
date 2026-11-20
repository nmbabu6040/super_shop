<?php

declare(strict_types=1);

namespace App\Http\Requests\Frontend\Auth;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise before validating so the uniqueness checks compare the stored form.
     */
    protected function prepareForValidation(): void
    {
        $normalizedInput = [];

        if (is_string($this->input('email'))) {
            $normalizedInput['email'] = Str::lower(trim($this->input('email')));
        }

        if (is_string($this->input('phone'))) {
            $normalizedInput['phone'] = PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone');
        }

        $this->merge($normalizedInput);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => [
                'nullable',
                'string',
                Rule::unique('users', 'phone'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (PhoneNumber::normalize((string) $value) === null) {
                        $fail(__('auth.invalid_phone'));
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('auth.name_label'),
            'email' => __('auth.email_label'),
            'phone' => __('auth.phone_label'),
            'password' => __('auth.password_label'),
        ];
    }
}
