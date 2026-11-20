<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Models\User;

class UpdateUserRequest extends UserFormRequest
{
    public function authorize(): bool
    {
        $editedUser = $this->route('user');

        return $editedUser instanceof User && $this->user()->can('update', $editedUser);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sharedRules($this->route('user'));
    }
}
