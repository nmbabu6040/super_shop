<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\AccountType;
use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            ? $this->user()->can('update', $account)
            : $this->user()->can('create', Account::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_postable' => $this->boolean('is_postable'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * The code is only validated when creating: it never changes afterwards.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedAccount = $this->route('account');

        $codeRules = $editedAccount instanceof Account
            ? []
            : ['code' => ['required', 'string', 'regex:/^[0-9]{3,10}$/', Rule::unique('accounts', 'code')]];

        return [
            ...$codeRules,
            ...$this->translatableRules('name', true, 150),
            'parent_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            // The type is taken from the parent when there is one.
            'type' => ['required_without:parent_id', 'nullable', Rule::enum(AccountType::class)],
            'is_postable' => ['boolean'],
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
            'code' => __('accounting.fields.code'),
            'parent_id' => __('accounting.fields.parent'),
            'type' => __('accounting.fields.type'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('accounting.errors.invalid_code'),
        ];
    }
}
