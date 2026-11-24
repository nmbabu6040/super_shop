<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Enums\ProductType;
use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductService;
use App\Support\Barcodes\BarcodeValidator;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        $normalizedInput = [
            'track_batches' => $this->boolean('track_batches'),
            'track_expiry' => $this->boolean('track_expiry'),
            'track_serial' => $this->boolean('track_serial'),
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'is_sold_online' => $this->boolean('is_sold_online'),
            'generate_barcode' => $this->boolean('generate_barcode'),
        ];

        if (is_string($this->input('slug'))) {
            $normalizedInput['slug'] = strtolower(trim($this->input('slug')));
        }

        if (is_string($this->input('sku'))) {
            $normalizedInput['sku'] = strtoupper(trim($this->input('sku')));
        }

        if (is_string($this->input('barcode'))) {
            $normalizedInput['barcode'] = trim($this->input('barcode'));
        }

        if (is_array($this->input('medicine'))) {
            $medicine = $this->input('medicine');
            $medicine['requires_prescription'] = filter_var($medicine['requires_prescription'] ?? true, FILTER_VALIDATE_BOOL);
            $medicine['is_controlled'] = filter_var($medicine['is_controlled'] ?? false, FILTER_VALIDATE_BOOL);
            $normalizedInput['medicine'] = $medicine;
        }

        $this->merge($normalizedInput);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedProduct = $this->route('product');
        $isEditing = $editedProduct instanceof Product;
        $variantId = $isEditing
            ? ProductVariant::withTrashed()->where('default_for_product_id', $editedProduct->getKey())->value('id')
            : null;
        $isMedicine = $this->input('product_type') === ProductType::Medicine->value;

        $priceRules = ['numeric', 'decimal:0,2', 'min:0', 'max:99999999999'];

        return [
            ...$this->translatableRules('name', true, 200),
            ...$this->translatableRules('description', false, 5000),
            'slug' => [
                'nullable',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($isEditing ? $editedProduct->getKey() : null),
            ],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            // The unit is chosen once: it is ignored when editing.
            'unit_id' => [$isEditing ? 'nullable' : 'required', 'integer', Rule::exists('units', 'id')],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'track_batches' => ['boolean'],
            'track_expiry' => ['boolean'],
            'track_serial' => ['boolean'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_sold_online' => ['boolean'],
            'reorder_level' => ['nullable', 'numeric', 'decimal:0,4', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'decimal:0,4', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'decimal:0,4', 'min:0'],

            'sku' => [
                'nullable',
                'string',
                'regex:/^[A-Z0-9][A-Z0-9._-]{1,59}$/',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'cost_price' => ['required', ...$priceRules],
            'selling_price' => ['required', ...$priceRules],
            'mrp' => ['nullable', ...$priceRules],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail) use ($variantId): void {
                    if (BarcodeValidator::detect((string) $value) === null) {
                        $fail(__('products.errors.barcode_invalid'));

                        return;
                    }

                    $isTaken = ProductBarcode::query()
                        ->where('barcode', $value)
                        ->when($variantId !== null, fn ($query) => $query->where('variant_id', '!=', $variantId))
                        ->exists();

                    if ($isTaken) {
                        $fail(__('products.errors.barcode_taken'));
                    }
                },
            ],
            'generate_barcode' => ['boolean'],

            'medicine' => ['nullable', 'array'],
            'medicine.generic_name' => [$isMedicine ? 'required' : 'nullable', 'string', 'max:150'],
            'medicine.manufacturer' => ['nullable', 'string', 'max:150'],
            'medicine.drug_category' => ['nullable', 'string', 'max:100'],
            'medicine.registration_number' => ['nullable', 'string', 'max:60'],
            'medicine.requires_prescription' => ['boolean'],
            'medicine.is_controlled' => ['boolean'],
            'medicine.storage_conditions' => ['nullable', 'string', 'max:255'],

            // SVG is not allowed: it can carry scripts.
            'images' => ['nullable', 'array', 'max:' . ProductService::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'primary_image_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Rules that compare several fields. They only run when the single-field rules passed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mrp = $this->input('mrp');

            if ($mrp !== null && $mrp !== '' && bccomp((string) $this->input('selling_price'), (string) $mrp, 2) === 1) {
                $validator->errors()->add('selling_price', __('products.errors.price_above_mrp'));
            }

            $maxStock = $this->input('max_stock');

            if (
                $maxStock !== null && $maxStock !== ''
                && bccomp((string) $maxStock, (string) ($this->input('min_stock') ?: '0'), 4) === -1
            ) {
                $validator->errors()->add('max_stock', __('products.errors.max_below_min'));
            }

            $this->validateImages($validator);
        });
    }

    private function validateImages(Validator $validator): void
    {
        $product = $this->route('product');
        $existingImageIds = $product instanceof Product
            ? ProductImage::query()->where('product_id', $product->getKey())->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];

        $removedImageIds = array_map('intval', (array) $this->input('remove_images', []));
        $keptImageIds = array_diff($existingImageIds, $removedImageIds);
        $newImageCount = count((array) $this->file('images', []));

        if (count($keptImageIds) + $newImageCount > ProductService::MAX_IMAGES) {
            $validator->errors()->add('images', __('products.errors.too_many_images', ['max' => ProductService::MAX_IMAGES]));
        }

        $primaryImageId = $this->input('primary_image_id');

        if ($primaryImageId !== null && $primaryImageId !== '' && ! in_array((int) $primaryImageId, $keptImageIds, true)) {
            $validator->errors()->add('primary_image_id', __('products.errors.invalid_primary_image'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->translatableAttributes('name', __('catalog.name')),
            ...$this->translatableAttributes('description', __('catalog.description')),
            'slug' => __('catalog.slug'),
            'category_id' => __('products.fields.category'),
            'brand_id' => __('products.fields.brand'),
            'unit_id' => __('products.fields.unit'),
            'tax_rate_id' => __('products.fields.tax_rate'),
            'product_type' => __('products.fields.product_type'),
            'sku' => __('products.fields.sku'),
            'cost_price' => __('products.fields.cost_price'),
            'selling_price' => __('products.fields.selling_price'),
            'mrp' => __('products.fields.mrp'),
            'barcode' => __('products.fields.barcode'),
            'reorder_level' => __('products.fields.reorder_level'),
            'min_stock' => __('products.fields.min_stock'),
            'max_stock' => __('products.fields.max_stock'),
            'medicine.generic_name' => __('products.fields.generic_name'),
            'images' => __('products.fields.images'),
            'images.*' => __('products.fields.images'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('catalog.invalid_slug'),
            'sku.regex' => __('products.errors.sku_invalid'),
        ];
    }
}
