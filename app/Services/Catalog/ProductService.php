<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\BarcodeType;
use App\Enums\ProductType;
use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariantBranchPrice;
use App\Support\Barcodes\BarcodeGenerator;
use App\Support\Barcodes\BarcodeValidator;
use App\Support\Media\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Product rules. A product always owns a default variant that holds SKU, prices and barcodes,
 * so stock, orders and the POS can always refer to a variant. Further variants (sizes, packs)
 * and branch prices hang on the same structure.
 */
final class ProductService
{
    public const MAX_IMAGES = 8;

    private const IMAGE_DIRECTORY = 'products';

    /**
     * @param  array<string, mixed>  $data  Validated form data (without the image files).
     * @param  list<UploadedFile>  $newImages
     */
    public function create(array $data, array $newImages = []): Product
    {
        $this->assertPrices($data);

        return DB::transaction(function () use ($data, $newImages): Product {
            $product = new Product();
            $product->fill([...$this->productAttributes($data), 'slug' => $this->resolveSlug($data)]);
            $product->save();

            $this->syncDefaultVariant($product, $data);
            $this->syncExtraVariants($product, $data['extra_variants'] ?? []);
            $this->syncMedicineDetails($product, $data);
            $this->syncImages($product, $newImages, [], null);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     * @param  list<int>  $allowedBranchIds  Branches whose prices the actor may set.
     */
    public function update(
        Product $product,
        array $data,
        array $newImages = [],
        array $removeImageIds = [],
        ?int $primaryImageId = null,
        array $allowedBranchIds = [],
    ): Product {
        $this->assertPrices($data);

        $filesToDelete = DB::transaction(function () use ($product, $data, $newImages, $removeImageIds, $primaryImageId, $allowedBranchIds): array {
            // The unit never changes after creation: stock quantities depend on it.
            $product->fill(Arr::except($this->productAttributes($data), ['unit_id']));

            if (! empty($data['slug'])) {
                $product->slug = $data['slug'];
            }

            $product->save();

            $this->syncDefaultVariant($product, $data);
            // A missing "extra_variants" field means: no extra variants (existing ones are removed).
            $this->syncExtraVariants($product, $data['extra_variants'] ?? []);
            $this->syncMedicineDetails($product, $data);

            // A missing "branch_prices" field means: leave branch prices as they are.
            if (array_key_exists('branch_prices', $data)) {
                $this->syncBranchPrices($product, (array) $data['branch_prices'], $allowedBranchIds);
            }

            return $this->syncImages($product, $newImages, $removeImageIds, $primaryImageId);
        });

        // Removed image files are deleted only after the database change succeeded.
        foreach ($filesToDelete as $path) {
            PublicImageStore::delete($path);
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            // Product first, then its variants: variants always carry a deleted_at that is not
            // earlier than the product's, which lets restore() bring back exactly these variants.
            $product->delete();

            ProductVariant::query()
                ->where('product_id', $product->getKey())
                ->get()
                ->each(static fn (ProductVariant $variant): ?bool => $variant->delete());
        });
    }

    public function restore(Product $product): void
    {
        if (
            Category::onlyTrashed()->whereKey($product->category_id)->exists()
            || ($product->brand_id !== null && Brand::onlyTrashed()->whereKey($product->brand_id)->exists())
        ) {
            throw BusinessRuleException::translated('products.errors.parent_deleted');
        }

        $deletedAt = $product->deleted_at;

        DB::transaction(function () use ($product, $deletedAt): void {
            $product->restore();

            ProductVariant::onlyTrashed()
                ->where('product_id', $product->getKey())
                ->where('deleted_at', '>=', $deletedAt)
                ->get()
                ->each(static fn (ProductVariant $variant): ?bool => $variant->restore());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productAttributes(array $data): array
    {
        $isMedicine = ($data['product_type'] ?? ProductType::General->value) === ProductType::Medicine->value;

        return [
            ...Arr::only($data, [
                'category_id',
                'brand_id',
                'unit_id',
                'tax_rate_id',
                'product_type',
                'name',
                'description',
            ]),
            // A medicine always tracks batches and expiry dates (expired stock must never be sold).
            'track_batches' => $isMedicine ? true : (bool) ($data['track_batches'] ?? false),
            'track_expiry' => $isMedicine ? true : (bool) ($data['track_expiry'] ?? false),
            'track_serial' => (bool) ($data['track_serial'] ?? false),
            'reorder_level' => $this->blankToDefault($data['reorder_level'] ?? null, '0'),
            'min_stock' => $this->blankToDefault($data['min_stock'] ?? null, '0'),
            'max_stock' => $this->blankToDefault($data['max_stock'] ?? null, null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_sold_online' => (bool) ($data['is_sold_online'] ?? true),
        ];
    }

    /**
     * No selling price (of any variant) can exceed its maximum retail price.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertPrices(array $data): void
    {
        $priceRows = [$data, ...array_values((array) ($data['extra_variants'] ?? []))];

        foreach ($priceRows as $row) {
            $mrp = $this->blankToDefault($row['mrp'] ?? null, null);

            if ($mrp !== null && bccomp((string) $row['selling_price'], (string) $mrp, 2) === 1) {
                throw BusinessRuleException::translated('products.errors.price_above_mrp');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncDefaultVariant(Product $product, array $data): void
    {
        $variant = ProductVariant::query()->where('default_for_product_id', $product->getKey())->first()
            ?? new ProductVariant();

        $hasExtraVariants = ($data['extra_variants'] ?? []) !== [];

        $variant->fill([
            // Options describe the variant (size, pack); they matter once there is more than one variant.
            'options' => $hasExtraVariants ? $this->normalizeOptions($data['variant_options'] ?? []) : null,
            'cost_price' => $data['cost_price'],
            'selling_price' => $data['selling_price'],
            'mrp' => $this->blankToDefault($data['mrp'] ?? null, null),
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $variant->product_id = $product->getKey();

        $requestedSku = $data['sku'] ?? null;
        $variant->sku = $requestedSku ?: ($variant->sku ?: $this->generateSku($product));

        // Not mass assignable: it marks this variant as the product's default one.
        $variant->forceFill(['default_for_product_id' => $product->getKey()])->save();

        $this->syncBarcodes($variant, $this->defaultVariantCodes($data), ! empty($data['generate_barcode']));
    }

    /**
     * Creates, updates and removes the variants other than the default one.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncExtraVariants(Product $product, array $rows): void
    {
        $existingVariants = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->whereNull('default_for_product_id')
            ->get()
            ->keyBy('id');

        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $variant = isset($row['id']) && $existingVariants->has((int) $row['id'])
                ? $existingVariants->get((int) $row['id'])
                : new ProductVariant();

            $variant->fill([
                'options' => $this->normalizeOptions($row['options'] ?? []),
                'cost_price' => $row['cost_price'],
                'selling_price' => $row['selling_price'],
                'mrp' => $this->blankToDefault($row['mrp'] ?? null, null),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $index + 1,
            ]);
            $variant->product_id = $product->getKey();
            $variant->sku = ($row['sku'] ?? null) ?: ($variant->sku ?: $this->generateSku($product, $index + 2));
            $variant->save();

            $this->syncBarcodes(
                $variant,
                BarcodeValidator::parseList($row['barcodes'] ?? null),
                ! empty($row['generate_barcode'])
            );

            $keptIds[] = $variant->getKey();
        }

        // Variants that are no longer in the form are removed (soft delete keeps their SKU and barcodes reserved).
        $existingVariants
            ->reject(static fn (ProductVariant $variant): bool => in_array($variant->getKey(), $keptIds, true))
            ->each(static fn (ProductVariant $variant): ?bool => $variant->delete());
    }

    /**
     * Barcodes of the default variant: the primary one first, then the additional ones.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function defaultVariantCodes(array $data): array
    {
        $codes = [];
        $primaryCode = trim((string) ($data['barcode'] ?? ''));

        if ($primaryCode !== '') {
            $codes[] = $primaryCode;
        }

        foreach (BarcodeValidator::parseList($data['extra_barcodes'] ?? null) as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Makes the variant carry exactly the given codes (the first one is primary).
     * No codes: all are removed, or one internal EAN-13 is generated when requested.
     *
     * @param  list<string>  $codes
     */
    private function syncBarcodes(ProductVariant $variant, array $codes, bool $generate): void
    {
        if ($codes === [] && $generate) {
            if ($variant->barcodes()->exists()) {
                return;
            }

            $codes = [BarcodeGenerator::internalEan13((int) $variant->getKey())];
        }

        if ($codes === []) {
            $variant->barcodes()->delete();

            return;
        }

        $variant->barcodes()->whereNotIn('barcode', $codes)->delete();

        foreach (array_values($codes) as $position => $code) {
            ProductBarcode::query()->updateOrCreate(
                ['variant_id' => $variant->getKey(), 'barcode' => $code],
                [
                    'type' => (BarcodeValidator::detect($code) ?? BarcodeType::Code128)->value,
                    'is_primary' => $position === 0,
                ]
            );
        }
    }

    /**
     * Medicine details exist only for medicines.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncMedicineDetails(Product $product, array $data): void
    {
        if (($data['product_type'] ?? null) !== ProductType::Medicine->value) {
            $product->medicineDetail()->delete();

            return;
        }

        $medicine = $data['medicine'] ?? [];

        $product->medicineDetail()->updateOrCreate([], [
            'generic_name' => $medicine['generic_name'],
            'manufacturer' => $this->blankToDefault($medicine['manufacturer'] ?? null, null),
            'drug_category' => $this->blankToDefault($medicine['drug_category'] ?? null, null),
            'registration_number' => $this->blankToDefault($medicine['registration_number'] ?? null, null),
            'requires_prescription' => (bool) ($medicine['requires_prescription'] ?? true),
            'is_controlled' => (bool) ($medicine['is_controlled'] ?? false),
            'storage_conditions' => $this->blankToDefault($medicine['storage_conditions'] ?? null, null),
        ]);
    }

    /**
     * Sets, changes or clears (blank) branch prices of the product's variants.
     * Only branches the actor may access are touched; a branch price can never exceed the MRP.
     *
     * @param  array<int|string, array<int|string, mixed>>  $prices  [variantId => [branchId => price]]
     * @param  list<int>  $allowedBranchIds
     */
    private function syncBranchPrices(Product $product, array $prices, array $allowedBranchIds): void
    {
        $variants = ProductVariant::query()->where('product_id', $product->getKey())->get()->keyBy('id');

        foreach ($prices as $variantId => $pricesByBranch) {
            $variant = $variants->get((int) $variantId);

            if ($variant === null) {
                continue;
            }

            foreach ((array) $pricesByBranch as $branchId => $price) {
                $branchId = (int) $branchId;

                if (! in_array($branchId, $allowedBranchIds, true)) {
                    continue;
                }

                // Looked up across branches on purpose: the actor's "working branch" focus must not
                // hide an existing row (that would try to create a duplicate). Write access is still
                // enforced by the model's branch guard.
                $existingPrice = VariantBranchPrice::withoutBranchScope()
                    ->where('variant_id', $variant->getKey())
                    ->where('branch_id', $branchId)
                    ->first();

                if ($price === null || $price === '') {
                    $existingPrice?->delete();

                    continue;
                }

                if ($variant->mrp !== null && bccomp((string) $price, (string) $variant->mrp, 2) === 1) {
                    throw BusinessRuleException::translated('variants.errors.branch_price_above_mrp');
                }

                if ($existingPrice !== null) {
                    $existingPrice->selling_price = $price;
                    $existingPrice->save();
                } else {
                    VariantBranchPrice::query()->create([
                        'variant_id' => $variant->getKey(),
                        'branch_id' => $branchId,
                        'selling_price' => $price,
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     * @return list<string> Paths of removed images (files are deleted after the commit).
     */
    private function syncImages(Product $product, array $newImages, array $removeImageIds, ?int $primaryImageId): array
    {
        $pathsToDelete = [];

        if ($removeImageIds !== []) {
            ProductImage::query()
                ->where('product_id', $product->getKey())
                ->whereIn('id', $removeImageIds)
                ->get()
                ->each(function (ProductImage $image) use (&$pathsToDelete): void {
                    $pathsToDelete[] = $image->path;
                    $image->delete();
                });
        }

        $nextSortOrder = (int) ProductImage::query()->where('product_id', $product->getKey())->max('sort_order') + 1;

        foreach ($newImages as $imageFile) {
            ProductImage::query()->create([
                'product_id' => $product->getKey(),
                'path' => PublicImageStore::store($imageFile, self::IMAGE_DIRECTORY),
                'sort_order' => $nextSortOrder++,
            ]);
        }

        $images = ProductImage::query()->where('product_id', $product->getKey())->orderBy('sort_order')->get();

        if ($images->isNotEmpty()) {
            // The chosen image, else the current primary, else the first one.
            $primaryImage = ($primaryImageId !== null ? $images->firstWhere('id', $primaryImageId) : null)
                ?? $images->firstWhere('primary_for_product_id', $product->getKey())
                ?? $images->first();

            // Two steps so the UNIQUE marker never collides while the primary image changes.
            ProductImage::query()->where('product_id', $product->getKey())->update(['primary_for_product_id' => null]);
            $primaryImage->forceFill(['primary_for_product_id' => $product->getKey()])->save();
        }

        return $pathsToDelete;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSlug(array $data): string
    {
        $baseSlug = ! empty($data['slug'])
            ? (string) $data['slug']
            : (Str::slug((string) ($data['name']['en'] ?? '')) ?: 'product');

        $candidate = $baseSlug;
        $suffix = 2;

        // Unique across deleted products too, so a public URL is never reused.
        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  int  $position  1 for the default variant, 2.. for the others.
     */
    private function generateSku(Product $product, int $position = 1): string
    {
        $baseSku = 'P' . str_pad((string) $product->getKey(), 6, '0', STR_PAD_LEFT);
        $firstChoice = $position === 1 ? $baseSku : $baseSku . '-' . $position;

        $candidate = $firstChoice;
        $suffix = 2;

        // Unique across deleted variants too, so a SKU is never reused.
        while (ProductVariant::withTrashed()->where('sku', $candidate)->exists()) {
            $candidate = $firstChoice . '-' . $suffix++;
        }

        return $candidate;
    }

    /**
     * [["name" => "size", "value" => "1 kg"], ...] becomes ["size" => "1 kg"] (null when empty).
     *
     * @param  array<int, array{name?: string, value?: string}>  $rows
     * @return array<string, string>|null
     */
    private function normalizeOptions(array $rows): ?array
    {
        $options = [];

        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));

            if ($name !== '' && $value !== '') {
                $options[$name] = $value;
            }
        }

        return $options === [] ? null : $options;
    }

    private function blankToDefault(mixed $value, mixed $default): mixed
    {
        return $value === null || $value === '' ? $default : $value;
    }
}
