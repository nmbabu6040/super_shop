<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Enums\RoleName;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends RoleFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name'),
                // The names of the built-in roles are reserved even before they exist in the database.
                Rule::notIn(array_map(static fn (RoleName $roleName): string => $roleName->value, RoleName::cases())),
            ],
            ...$this->permissionRules(),
        ];
    }
}
