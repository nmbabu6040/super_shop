<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\BrandRequest;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brandService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);

        $searchTerm = trim((string) $request->query('search', ''));
        $activeFilter = (string) $request->query('active', '');
        $canSeeDeleted = $request->user()->hasAnyPermission(['brands.restore', 'brands.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        $brands = Brand::query()
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name->en', 'like', "%{$searchTerm}%")
                        ->orWhere('name->bn', 'like', "%{$searchTerm}%")
                        ->orWhere('slug', 'like', "%{$searchTerm}%");
                });
            })
            ->when(in_array($activeFilter, ['0', '1'], true), fn (Builder $query) => $query->where('is_active', $activeFilter === '1'))
            ->when($showDeleted, fn (Builder $query) => $query->onlyTrashed())
            ->orderBy('sort_order')
            ->orderBy('name->en')
            ->paginate(15)
            ->withQueryString();

        return view('admin.catalog.brands.index', [
            'brands' => $brands,
            'searchTerm' => $searchTerm,
            'activeFilter' => $activeFilter,
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);

        return view('admin.catalog.brands.form', ['brand' => null]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $this->brandService->create(
            Arr::except($request->validated(), ['logo']),
            $request->file('logo'),
        );

        return redirect()->route('admin.brands.index')->with('success', __('catalog.brands.created'));
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);

        return view('admin.catalog.brands.form', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->brandService->update(
            $brand,
            Arr::except($request->validated(), ['logo']),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return redirect()->route('admin.brands.index')->with('success', __('catalog.brands.updated'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        $this->brandService->delete($brand);

        return redirect()->route('admin.brands.index')->with('success', __('catalog.brands.deleted'));
    }

    public function restore(Brand $brand): RedirectResponse
    {
        Gate::authorize('restore', $brand);

        $this->brandService->restore($brand);

        return redirect()->route('admin.brands.index')->with('success', __('catalog.brands.restored'));
    }
}
