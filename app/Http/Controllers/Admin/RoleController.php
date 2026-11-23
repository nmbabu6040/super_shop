<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Services\Rbac\RoleService;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get(),
            'systemRoleNames' => array_map(static fn (RoleName $roleName): string => $roleName->value, RoleName::cases()),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.form', $this->formData($request, null));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleService->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('permissions') ?? [],
        );

        return redirect()->route('admin.roles.index')->with('success', __('roles.created'));
    }

    public function edit(Request $request, Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.form', $this->formData($request, $role));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->update(
            $request->user(),
            $role,
            $request->validated('name'),
            $request->validated('permissions') ?? [],
        );

        return redirect()->route('admin.roles.index')->with('success', __('roles.updated'));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roleService->delete($request->user(), $role);

        return redirect()->route('admin.roles.index')->with('success', __('roles.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Role $role): array
    {
        $systemRole = $role !== null ? RoleName::tryFrom($role->name) : null;

        return [
            'role' => $role,
            'modules' => PermissionCatalog::modules(),
            'grantable' => $this->roleService->grantablePermissionNames($request->user()),
            'selected' => $role?->permissions()->pluck('name')->all() ?? [],
            'isSystem' => $systemRole !== null,
            'isSuperAdmin' => $systemRole === RoleName::SuperAdmin,
            'isCustomer' => $systemRole === RoleName::Customer,
        ];
    }
}
