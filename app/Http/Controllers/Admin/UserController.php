<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\RoleAssignmentService;
use App\Services\Users\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $searchTerm = trim((string) $request->query('search', ''));
        $roleFilter = (string) $request->query('role', '');
        $statusFilter = UserStatus::tryFrom((string) $request->query('status', ''));
        $branchFilter = (string) $request->query('branch', '');
        $canSeeDeleted = $actor->hasAnyPermission(['users.restore', 'users.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        // Branch-bound actors only ever see staff who work in their own branches.
        $actorBranchIds = $actor->has_all_branch_access ? null : $actor->branches()->pluck('branches.id')->all();

        $staffUsers = User::query()
            // Staff = everyone who is not a customer-only account.
            ->where(function (Builder $staffQuery): void {
                $staffQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', '!=', 'customer'))
                    ->orWhereDoesntHave('roles');
            })
            ->when($actorBranchIds !== null, fn (Builder $query) => $query->whereHas(
                'branches',
                fn (Builder $branchQuery) => $branchQuery->whereIn('branches.id', $actorBranchIds)
            ))
            // Eager loading also covers what the policy needs, so no query runs per table row.
            ->with(['roles.permissions', 'permissions', 'branches'])
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('username', 'like', "%{$searchTerm}%")
                        ->orWhere('phone', 'like', "%{$searchTerm}%");
                });
            })
            ->when($roleFilter !== '', fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $roleQuery) => $roleQuery->where('name', $roleFilter)
            ))
            ->when($statusFilter !== null, fn (Builder $query) => $query->where('status', $statusFilter->value))
            ->when(ctype_digit($branchFilter), fn (Builder $query) => $query->whereHas(
                'branches',
                fn (Builder $branchQuery) => $branchQuery->where('branches.id', (int) $branchFilter)
            ))
            ->when($showDeleted, fn (Builder $query) => $query->onlyTrashed())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'staffUsers' => $staffUsers,
            'roleNames' => Role::query()->where('name', '!=', 'customer')->orderBy('name')->pluck('name'),
            'statuses' => UserStatus::cases(),
            'branches' => $actor->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'searchTerm' => $searchTerm,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter?->value,
            'branchFilter' => $branchFilter,
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', $this->formData($request->user(), null));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->user(), $request->validated());

        return redirect()->route('admin.users.index')->with('success', __('users.created'));
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', $this->formData($request->user(), $user->load(['roles', 'branches'])));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($request->user(), $user, $request->validated());

        return redirect()->route('admin.users.index')->with('success', __('users.updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->userService->delete($request->user(), $user);

        return redirect()->route('admin.users.index')->with('success', __('users.deleted'));
    }

    public function restore(User $user): RedirectResponse
    {
        Gate::authorize('restore', $user);

        $this->userService->restore($user);

        return redirect()->route('admin.users.index')->with('success', __('users.restored'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);

        $this->userService->suspend($request->user(), $user);

        return redirect()->route('admin.users.index')->with('success', __('users.suspended'));
    }

    public function reactivate(User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $this->userService->reactivate($user);

        return redirect()->route('admin.users.index')->with('success', __('users.reactivated'));
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('sendInvitation', $user);

        $this->userService->resendInvitation($request->user(), $user);

        return redirect()->route('admin.users.index')->with('success', __('users.invitation_sent'));
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('sendPasswordReset', $user);

        $this->userService->sendPasswordResetLink($user);

        return redirect()->route('admin.users.index')->with('success', __('users.reset_link_sent'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $actor, ?User $staffUser): array
    {
        return [
            'staffUser' => $staffUser,
            'assignableRoles' => $this->roleAssignmentService->assignableFor($actor),
            'branches' => $actor->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']),
            'canGrantAllBranches' => (bool) $actor->has_all_branch_access,
        ];
    }
}
