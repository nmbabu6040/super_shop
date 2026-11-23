<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\CategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\CategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly CategoryTree $categoryTree,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);

        $user = $request->user();
        $searchTerm = trim((string) $request->query('search', ''));
        $activeFilter = (string) $request->query('active', '');
        $canSeeDeleted = $user->hasAnyPermission(['categories.restore', 'categories.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        $isFiltered = $searchTerm !== '' || in_array($activeFilter, ['0', '1'], true) || $showDeleted;

        $categories = Category::query()
            ->with('parent')
            ->withCount('children')
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name->en', 'like', "%{$searchTerm}%")
                        ->orWhere('name->bn', 'like', "%{$searchTerm}%")
                        ->orWhere('slug', 'like', "%{$searchTerm}%");
                });
            })
            ->when(in_array($activeFilter, ['0', '1'], true), fn (Builder $query) => $query->where('is_active', $activeFilter === '1'))
            ->when($showDeleted, fn (Builder $query) => $query->onlyTrashed())
            ->get();

        // Without filters the categories are shown as an indented tree; with filters as a flat list.
        $rows = $isFiltered
            ? $categories->map(static fn (Category $category): array => ['category' => $category, 'depth' => 0])->all()
            : $this->categoryTree->flatten($categories);

        return view('admin.catalog.categories.index', [
            'rows' => $rows,
            'searchTerm' => $searchTerm,
            'activeFilter' => $activeFilter,
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('admin.catalog.categories.form', [
            'category' => null,
            'parentOptions' => $this->parentOptions(null),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create(
            Arr::except($request->validated(), ['image']),
            $request->file('image'),
        );

        return redirect()->route('admin.categories.index')->with('success', __('catalog.categories.created'));
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.catalog.categories.form', [
            'category' => $category,
            'parentOptions' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update(
            $category,
            Arr::except($request->validated(), ['image']),
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()->route('admin.categories.index')->with('success', __('catalog.categories.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $this->categoryService->delete($category);

        return redirect()->route('admin.categories.index')->with('success', __('catalog.categories.deleted'));
    }

    public function restore(Category $category): RedirectResponse
    {
        Gate::authorize('restore', $category);

        $this->categoryService->restore($category);

        return redirect()->route('admin.categories.index')->with('success', __('catalog.categories.restored'));
    }

    /**
     * Possible parents as an indented list. A category cannot be placed below itself.
     *
     * @return array<int, string>
     */
    private function parentOptions(?Category $editing): array
    {
        $excludedIds = $editing !== null
            ? [$editing->getKey(), ...$this->categoryService->descendantIds($editing)]
            : [];

        $options = [];

        foreach ($this->categoryTree->flatten(Category::query()->get()) as $row) {
            if (in_array($row['category']->getKey(), $excludedIds, true)) {
                continue;
            }

            $options[$row['category']->getKey()] = str_repeat('— ', $row['depth']) . $row['category']->name;
        }

        return $options;
    }
}
