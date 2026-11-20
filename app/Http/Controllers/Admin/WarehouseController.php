<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Admin\Warehouse\UpdateWarehouseRequest;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organization\WarehouseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouseService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        $user = $request->user();
        $searchTerm = trim((string) $request->query('search', ''));
        $branchFilter = (string) $request->query('branch', '');
        $typeFilter = WarehouseType::tryFrom((string) $request->query('type', ''));
        $activeFilter = (string) $request->query('active', '');
        $canSeeDeleted = $user->hasAnyPermission(['warehouses.restore', 'warehouses.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        // The Warehouse model applies branch isolation automatically (own branches + central).
        $warehouses = Warehouse::query()
            ->with('branch:id,name')
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%");
                });
            })
            ->when($branchFilter === 'central', fn (Builder $query) => $query->whereNull('branch_id'))
            ->when(ctype_digit($branchFilter), fn (Builder $query) => $query->where('branch_id', (int) $branchFilter))
            ->when($typeFilter !== null, fn (Builder $query) => $query->where('type', $typeFilter->value))
            ->when(in_array($activeFilter, ['0', '1'], true), fn (Builder $query) => $query->where('is_active', $activeFilter === '1'))
            ->when($showDeleted, fn (Builder $query) => $query->onlyTrashed())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.warehouses.index', [
            'warehouses' => $warehouses,
            'branches' => $this->branchOptions($user),
            'types' => WarehouseType::cases(),
            'searchTerm' => $searchTerm,
            'branchFilter' => $branchFilter,
            'typeFilter' => $typeFilter?->value,
            'activeFilter' => $activeFilter,
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Warehouse::class);

        $user = $request->user();

        return view('admin.warehouses.form', [
            'warehouse' => null,
            'branches' => $this->branchOptions($user),
            'managers' => $this->managerOptions(),
            // Only head office users may create a central warehouse.
            'types' => $user->has_all_branch_access
                ? [WarehouseType::Storage, WarehouseType::Central]
                : [WarehouseType::Storage],
        ]);
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->warehouseService->create($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', __('admin.warehouses.created'));
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('admin.warehouses.form', [
            'warehouse' => $warehouse->load('branch:id,name'),
            'branches' => collect(),
            'managers' => $this->managerOptions(),
            'types' => [],
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseService->update($warehouse, $request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', __('admin.warehouses.updated'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse);

        return redirect()->route('admin.warehouses.index')->with('success', __('admin.warehouses.deleted'));
    }

    public function restore(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('restore', $warehouse);

        $this->warehouseService->restore($warehouse);

        return redirect()->route('admin.warehouses.index')->with('success', __('admin.warehouses.restored'));
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchOptions(User $user): Collection
    {
        return $user->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']);
    }

    /**
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
