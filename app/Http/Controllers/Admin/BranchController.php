<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BranchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Branch\StoreBranchRequest;
use App\Http\Requests\Admin\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Organization\BranchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Branch::class);

        $user = $request->user();
        $searchTerm = trim((string) $request->query('search', ''));
        $statusFilter = BranchStatus::tryFrom((string) $request->query('status', ''));
        $canSeeDeleted = $user->hasAnyPermission(['branches.restore', 'branches.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        // Only branches this user may access are ever listed.
        $branches = $user->accessibleBranchesQuery()
            ->with('manager:id,name')
            ->withCount(['warehouses', 'users'])
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%")
                        ->orWhere('city', 'like', "%{$searchTerm}%");
                });
            })
            ->when($statusFilter !== null, fn (Builder $query) => $query->where('status', $statusFilter->value))
            ->when($showDeleted, fn (Builder $query) => $query->onlyTrashed())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.branches.index', [
            'branches' => $branches,
            'searchTerm' => $searchTerm,
            'statusFilter' => $statusFilter?->value,
            'statuses' => BranchStatus::cases(),
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Branch::class);

        return view('admin.branches.form', [
            'branch' => null,
            'managers' => $this->managerOptions(),
            'statuses' => BranchStatus::cases(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->branchService->create(
            Arr::except($request->validated(), ['logo']),
            $request->file('logo'),
        );

        return redirect()->route('admin.branches.index')->with('success', __('admin.branches.created'));
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return view('admin.branches.form', [
            'branch' => $branch,
            'managers' => $this->managerOptions(),
            'statuses' => BranchStatus::cases(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->branchService->update(
            $branch,
            Arr::except($request->validated(), ['logo', 'remove_logo']),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return redirect()->route('admin.branches.index')->with('success', __('admin.branches.updated'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        $this->branchService->delete($branch);

        return redirect()->route('admin.branches.index')->with('success', __('admin.branches.deleted'));
    }

    public function restore(Branch $branch): RedirectResponse
    {
        Gate::authorize('restore', $branch);

        $this->branchService->restore($branch);

        return redirect()->route('admin.branches.index')->with('success', __('admin.branches.restored'));
    }

    /**
     * Active staff members who can be chosen as a branch manager.
     *
     * @return Collection<int, User>
     */
    private function managerOptions(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', '!=', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
