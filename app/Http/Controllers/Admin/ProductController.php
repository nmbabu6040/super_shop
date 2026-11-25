<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantBranchPrice;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly CategoryTree $categoryTree,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $searchTerm = trim((string) $request->query('search', ''));
        $categoryFilter = (int) $request->query('category_id', 0);
        $brandFilter = (int) $request->query('brand_id', 0);
        $typeFilter = ProductType::tryFrom((string) $request->query('type', ''));
        $activeFilter = (string) $request->query('active', '');
        $canSeeDeleted = $request->user()->hasAnyPermission(['products.restore', 'products.manage']);
        $showDeleted = $canSeeDeleted && $request->boolean('trashed');

        // Choosing a category also shows the products of all its subcategories.
        $categoryIds = [];

        if ($categoryFilter > 0) {
            $filterCategory = Category::query()->find($categoryFilter);
            $categoryIds = $filterCategory !== null
                ? [$filterCategory->getKey(), ...$this->categoryService->descendantIds($filterCategory)]
                : [];
        }

        $products = Product::query()
            ->with(['category', 'brand', 'unit', 'primaryImage', 'defaultVariant.primaryBarcode'])
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->where('name->en', 'like', "%{$searchTerm}%")
                        ->orWhere('name->bn', 'like', "%{$searchTerm}%")
                        ->orWhere('slug', 'like', "%{$searchTerm}%")
                        ->orWhereHas('variants', function (Builder $variantQuery) use ($searchTerm): void {
                            $variantQuery->where('sku', 'like', "%{$searchTerm}%")
                                ->orWhereHas('barcodes', fn(Builder $barcodeQuery) => $barcodeQuery->where('barcode', 'like', "%{$searchTerm}%"));
                        });
                });
            })
            ->when($categoryIds !== [], fn(Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->when($brandFilter > 0, fn(Builder $query) => $query->where('brand_id', $brandFilter))
            ->when($typeFilter !== null, fn(Builder $query) => $query->where('product_type', $typeFilter->value))
            ->when(in_array($activeFilter, ['0', '1'], true), fn(Builder $query) => $query->where('is_active', $activeFilter === '1'))
            ->when($showDeleted, fn(Builder $query) => $query->onlyTrashed())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.catalog.products.index', [
            'products' => $products,
            'categoryOptions' => $this->categoryOptions(),
            'brands' => Brand::query()->orderBy('name->en')->get(),
            'types' => ProductType::cases(),
            'searchTerm' => $searchTerm,
            'categoryFilter' => $categoryFilter,
            'brandFilter' => $brandFilter,
            'typeFilter' => $typeFilter?->value,
            'activeFilter' => $activeFilter,
            'canSeeDeleted' => $canSeeDeleted,
            'showDeleted' => $showDeleted,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.catalog.products.form', $this->formData($request->user(), null));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->productService->create(
            Arr::except($request->validated(), ['images', 'remove_images', 'primary_image_id', 'branch_prices']),
            $request->file('images') ?? [],
        );

        return redirect()->route('admin.products.index')->with('success', __('products.created'));
    }

    public function edit(Request $request, Product $product): View
    {
        Gate::authorize('update', $product);

        return view('admin.catalog.products.form', $this->formData(
            $request->user(),
            $product->load([
                'defaultVariant.barcodes',
                'defaultVariant.primaryBarcode',
                'variants.barcodes',
                'variants.primaryBarcode',
                'images',
                'medicineDetail',
                'unit',
            ])
        ));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $primaryImageId = $request->input('primary_image_id');

        $this->productService->update(
            $product,
            Arr::except($request->validated(), ['images', 'remove_images', 'primary_image_id']),
            $request->file('images') ?? [],
            array_map('intval', (array) $request->input('remove_images', [])),
            $primaryImageId === null || $primaryImageId === '' ? null : (int) $primaryImageId,
            $request->user()->accessibleBranchesQuery()->pluck('id')->map(fn($id): int => (int) $id)->all(),
        );

        return redirect()->route('admin.products.index')->with('success', __('products.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()->route('admin.products.index')->with('success', __('products.deleted'));
    }

    public function restore(Product $product): RedirectResponse
    {
        Gate::authorize('restore', $product);

        $this->productService->restore($product);

        return redirect()->route('admin.products.index')->with('success', __('products.restored'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user, ?Product $product): array
    {
        $accessibleBranches = $user->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']);

        return [
            'product' => $product,
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => Brand::query()->orderBy('name->en')->get()->mapWithKeys(fn(Brand $brand): array => [$brand->id => (string) $brand->name])->all(),
            'unitOptions' => Unit::query()->orderBy('code')->get()->mapWithKeys(fn(Unit $unit): array => [$unit->id => $unit->name . ' (' . $unit->symbol . ')'])->all(),
            'taxRateOptions' => TaxRate::query()->orderBy('rate', 'desc')->get()->mapWithKeys(fn(TaxRate $taxRate): array => [$taxRate->id => $taxRate->name . ' (' . rtrim(rtrim($taxRate->rate, '0'), '.') . '%)'])->all(),
            'defaultTaxRateId' => TaxRate::query()->whereNotNull('default_marker')->value('id'),
            'typeOptions' => collect(ProductType::cases())->mapWithKeys(fn(ProductType $type): array => [$type->value => $type->label()])->all(),
            'accessibleBranches' => $accessibleBranches,
            'branchPrices' => $this->branchPricesFor($product, $accessibleBranches),
        ];
    }

    /**
     * Existing branch prices as [variantId => [branchId => price]], limited to branches the user may see.
     *
     * @param  Collection<int, \App\Models\Branch>  $accessibleBranches
     * @return array<int, array<int, string>>
     */
    private function branchPricesFor(?Product $product, Collection $accessibleBranches): array
    {
        if ($product === null) {
            return [];
        }

        return VariantBranchPrice::withoutBranchScope()
            ->whereIn('variant_id', $product->variants->pluck('id'))
            ->whereIn('branch_id', $accessibleBranches->pluck('id'))
            ->get()
            ->groupBy('variant_id')
            ->map(fn(Collection $rows): array => $rows->pluck('selling_price', 'branch_id')->all())
            ->all();
    }

    /**
     * Categories as an indented list.
     *
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        $options = [];

        foreach ($this->categoryTree->flatten(Category::query()->get()) as $row) {
            $options[$row['category']->getKey()] = str_repeat('— ', $row['depth']) . $row['category']->name;
        }

        return $options;
    }
}
