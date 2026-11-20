<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Models\User;

class StoreUserRequest extends UserFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sharedRules(null);
    }
}
