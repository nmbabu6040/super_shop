<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Enums\RoleName;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends RoleFormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && $this->user()->can('update', $role);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        // System roles keep their names, so no name is accepted for them.
        $nameRules = RoleName::tryFrom($role->name) !== null
            ? []
            : ['name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->ignore($role->id),
                Rule::notIn(array_map(static fn (RoleName $roleName): string => $roleName->value, RoleName::cases())),
            ]];

        return [...$nameRules, ...$this->permissionRules()];
    }
}
