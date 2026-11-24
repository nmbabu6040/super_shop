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
use App\Support\Barcodes\BarcodeGenerator;
use App\Support\Barcodes\BarcodeValidator;
use App\Support\Media\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Product rules. A product always owns a default variant that holds SKU, prices and barcode,
 * so stock, orders and the POS can always refer to a variant (extra variants arrive later).
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
            $this->syncMedicineDetails($product, $data);
            $this->syncImages($product, $newImages, [], null);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     */
    public function update(Product $product, array $data, array $newImages = [], array $removeImageIds = [], ?int $primaryImageId = null): Product
    {
        $this->assertPrices($data);

        $filesToDelete = DB::transaction(function () use ($product, $data, $newImages, $removeImageIds, $primaryImageId): array {
            // The unit never changes after creation: stock quantities depend on it.
            $product->fill(Arr::except($this->productAttributes($data), ['unit_id']));

            if (! empty($data['slug'])) {
                $product->slug = $data['slug'];
            }

            $product->save();

            $this->syncDefaultVariant($product, $data);
            $this->syncMedicineDetails($product, $data);

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
                'reorder_level',
                'min_stock',
                'max_stock',
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
     * The selling price can never exceed the maximum retail price.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertPrices(array $data): void
    {
        $mrp = $this->blankToDefault($data['mrp'] ?? null, null);

        if ($mrp !== null && bccomp((string) $data['selling_price'], (string) $mrp, 2) === 1) {
            throw BusinessRuleException::translated('products.errors.price_above_mrp');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncDefaultVariant(Product $product, array $data): void
    {
        $variant = ProductVariant::query()->where('default_for_product_id', $product->getKey())->first()
            ?? new ProductVariant();

        $variant->fill([
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

        $this->syncBarcode($variant, $data);
    }

    /**
     * One primary barcode for now. A blank field removes it, "generate" creates an internal EAN-13.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncBarcode(ProductVariant $variant, array $data): void
    {
        $requestedBarcode = trim((string) ($data['barcode'] ?? ''));
        $generate = ! empty($data['generate_barcode']);

        if ($requestedBarcode === '' && $generate) {
            if ($variant->barcodes()->exists()) {
                return;
            }

            $requestedBarcode = BarcodeGenerator::internalEan13((int) $variant->getKey());
        }

        if ($requestedBarcode === '') {
            $variant->barcodes()->delete();

            return;
        }

        $variant->barcodes()->where('barcode', '!=', $requestedBarcode)->delete();

        ProductBarcode::query()->updateOrCreate(
            ['variant_id' => $variant->getKey(), 'barcode' => $requestedBarcode],
            [
                'type' => (BarcodeValidator::detect($requestedBarcode) ?? BarcodeType::Code128)->value,
                'is_primary' => true,
            ]
        );
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

    private function generateSku(Product $product): string
    {
        $baseSku = 'P' . str_pad((string) $product->getKey(), 6, '0', STR_PAD_LEFT);
        $candidate = $baseSku;
        $suffix = 2;

        while (ProductVariant::withTrashed()->where('sku', $candidate)->exists()) {
            $candidate = $baseSku . '-' . $suffix++;
        }

        return $candidate;
    }

    private function blankToDefault(mixed $value, mixed $default): mixed
    {
        return $value === null || $value === '' ? $default : $value;
    }
}
