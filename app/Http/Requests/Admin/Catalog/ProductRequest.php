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
            'variant_options' => $this->normalizeOptionRows($this->input('variant_options')),
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

        if (is_array($this->input('extra_variants'))) {
            $normalizedInput['extra_variants'] = collect($this->input('extra_variants'))
                ->values()
                ->map(function (mixed $row): array {
                    $row = is_array($row) ? $row : [];

                    return [
                        ...$row,
                        'sku' => isset($row['sku']) && is_string($row['sku']) ? strtoupper(trim($row['sku'])) : null,
                        'options' => $this->normalizeOptionRows($row['options'] ?? null),
                        'generate_barcode' => filter_var($row['generate_barcode'] ?? false, FILTER_VALIDATE_BOOL),
                        'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                    ];
                })
                ->all();
        }

        $this->merge($normalizedInput);
    }

    /**
     * Drops empty attribute rows and lower-cases attribute names.
     *
     * @return list<array{name: string, value: string}>
     */
    private function normalizeOptionRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalizedRows = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));

            if ($name !== '' || $value !== '') {
                $normalizedRows[] = ['name' => $name, 'value' => $value];
            }
        }

        return $normalizedRows;
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
        $optionNameRules = ['required', 'string', 'max:30', 'regex:/^[a-z0-9 _-]+$/'];
        $optionValueRules = ['required', 'string', 'max:60'];

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

            // Default variant
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
            'extra_barcodes' => ['nullable', 'string', 'max:2000'],
            'generate_barcode' => ['boolean'],
            'variant_options' => ['nullable', 'array', 'max:5'],
            'variant_options.*.name' => $optionNameRules,
            'variant_options.*.value' => $optionValueRules,

            // Further variants
            'extra_variants' => ['nullable', 'array', 'max:49'],
            'extra_variants.*.id' => ['nullable', 'integer'],
            'extra_variants.*.sku' => ['nullable', 'string', 'regex:/^[A-Z0-9][A-Z0-9._-]{1,59}$/'],
            'extra_variants.*.options' => ['nullable', 'array', 'max:5'],
            'extra_variants.*.options.*.name' => $optionNameRules,
            'extra_variants.*.options.*.value' => $optionValueRules,
            'extra_variants.*.cost_price' => ['required', ...$priceRules],
            'extra_variants.*.selling_price' => ['required', ...$priceRules],
            'extra_variants.*.mrp' => ['nullable', ...$priceRules],
            'extra_variants.*.barcodes' => ['nullable', 'string', 'max:2000'],
            'extra_variants.*.generate_barcode' => ['boolean'],
            'extra_variants.*.is_active' => ['boolean'],

            // Branch prices: [variantId => [branchId => price]] (blank = use the normal price)
            'branch_prices' => ['nullable', 'array'],
            'branch_prices.*' => ['array'],
            'branch_prices.*.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999999'],

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
     * Rules that compare several fields or look at the database. They only run when the
     * single-field rules passed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validatePrices($validator);
            $this->validateStock($validator);
            $this->validateVariants($validator);
            $this->validateBranchPrices($validator);
            $this->validateImages($validator);
        });
    }

    private function validatePrices(Validator $validator): void
    {
        $mrp = $this->input('mrp');

        if ($mrp !== null && $mrp !== '' && bccomp((string) $this->input('selling_price'), (string) $mrp, 2) === 1) {
            $validator->errors()->add('selling_price', __('products.errors.price_above_mrp'));
        }

        foreach (array_values((array) $this->input('extra_variants', [])) as $index => $row) {
            $rowMrp = $row['mrp'] ?? null;

            if ($rowMrp !== null && $rowMrp !== '' && bccomp((string) $row['selling_price'], (string) $rowMrp, 2) === 1) {
                $validator->errors()->add("extra_variants.{$index}.selling_price", __('products.errors.price_above_mrp'));
            }
        }
    }

    private function validateStock(Validator $validator): void
    {
        $maxStock = $this->input('max_stock');

        if (
            $maxStock !== null && $maxStock !== ''
            && bccomp((string) $maxStock, (string) ($this->input('min_stock') ?: '0'), 4) === -1
        ) {
            $validator->errors()->add('max_stock', __('products.errors.max_below_min'));
        }
    }

    /**
     * SKU, barcode and attribute rules across the default variant and the extra variants.
     */
    private function validateVariants(Validator $validator): void
    {
        $product = $this->route('product');
        $defaultVariantId = $product instanceof Product
            ? ProductVariant::query()->where('default_for_product_id', $product->getKey())->value('id')
            : null;
        $ownExtraIds = $product instanceof Product
            ? ProductVariant::query()->where('product_id', $product->getKey())->whereNull('default_for_product_id')
            ->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];

        $extraRows = array_values((array) $this->input('extra_variants', []));

        $entries = [[
            'id' => $defaultVariantId === null ? null : (int) $defaultVariantId,
            'sku' => $this->input('sku'),
            'options' => (array) $this->input('variant_options', []),
            'barcodes' => $this->defaultVariantCodes(),
            'skuKey' => 'sku',
            'optionsKey' => 'variant_options',
            'barcodesKey' => 'extra_barcodes',
        ]];

        foreach ($extraRows as $index => $row) {
            $rowId = isset($row['id']) && $row['id'] !== null ? (int) $row['id'] : null;

            if ($rowId !== null && ! in_array($rowId, $ownExtraIds, true)) {
                $validator->errors()->add("extra_variants.{$index}.id", __('variants.errors.invalid_variant'));
            }

            $entries[] = [
                'id' => $rowId,
                'sku' => $row['sku'] ?? null,
                'options' => (array) ($row['options'] ?? []),
                'barcodes' => BarcodeValidator::parseList($row['barcodes'] ?? null),
                'skuKey' => "extra_variants.{$index}.sku",
                'optionsKey' => "extra_variants.{$index}.options",
                'barcodesKey' => "extra_variants.{$index}.barcodes",
            ];
        }

        $seenSkus = [];
        $seenBarcodes = [];
        $seenOptionSets = [];

        foreach ($entries as $entry) {
            $sku = $entry['sku'];

            if ($sku !== null && $sku !== '') {
                $isTaken = ProductVariant::withTrashed()
                    ->where('sku', $sku)
                    ->when($entry['id'] !== null, fn ($query) => $query->where('id', '!=', $entry['id']))
                    ->exists();

                if (in_array($sku, $seenSkus, true)) {
                    $validator->errors()->add($entry['skuKey'], __('variants.errors.duplicate_sku'));
                } elseif ($isTaken) {
                    $validator->errors()->add($entry['skuKey'], __('variants.errors.sku_taken'));
                }

                $seenSkus[] = $sku;
            }

            foreach ($entry['barcodes'] as $code) {
                if (BarcodeValidator::detect($code) === null) {
                    $validator->errors()->add($entry['barcodesKey'], __('products.errors.barcode_invalid') . ' (' . $code . ')');

                    continue;
                }

                $isTaken = ProductBarcode::query()
                    ->where('barcode', $code)
                    ->when($entry['id'] !== null, fn ($query) => $query->where('variant_id', '!=', $entry['id']))
                    ->exists();

                if (in_array($code, $seenBarcodes, true)) {
                    $validator->errors()->add($entry['barcodesKey'], __('variants.errors.duplicate_barcode') . ' (' . $code . ')');
                } elseif ($isTaken) {
                    $validator->errors()->add($entry['barcodesKey'], __('products.errors.barcode_taken') . ' (' . $code . ')');
                }

                $seenBarcodes[] = $code;
            }
        }

        // With several variants, every variant must say how it differs from the others.
        if ($extraRows !== []) {
            foreach ($entries as $entry) {
                $optionMap = [];

                foreach ($entry['options'] as $option) {
                    $optionMap[(string) $option['name']] = (string) $option['value'];
                }

                if ($optionMap === []) {
                    $validator->errors()->add($entry['optionsKey'], __('variants.errors.options_required'));

                    continue;
                }

                ksort($optionMap);
                $fingerprint = strtolower((string) json_encode($optionMap, JSON_UNESCAPED_UNICODE));

                if (in_array($fingerprint, $seenOptionSets, true)) {
                    $validator->errors()->add($entry['optionsKey'], __('variants.errors.duplicate_options'));
                }

                $seenOptionSets[] = $fingerprint;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function defaultVariantCodes(): array
    {
        $codes = [];
        $primaryCode = trim((string) $this->input('barcode'));

        if ($primaryCode !== '') {
            $codes[] = $primaryCode;
        }

        foreach (BarcodeValidator::parseList($this->input('extra_barcodes')) as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function validateBranchPrices(Validator $validator): void
    {
        $branchPrices = (array) $this->input('branch_prices', []);

        if ($branchPrices === []) {
            return;
        }

        $product = $this->route('product');
        $variantIds = $product instanceof Product
            ? ProductVariant::query()->where('product_id', $product->getKey())->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $accessibleBranchIds = $this->user()->accessibleBranchesQuery()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($branchPrices as $variantId => $pricesByBranch) {
            if (! in_array((int) $variantId, $variantIds, true)) {
                $validator->errors()->add("branch_prices.{$variantId}", __('variants.errors.invalid_variant'));

                continue;
            }

            foreach (array_keys((array) $pricesByBranch) as $branchId) {
                if (! in_array((int) $branchId, $accessibleBranchIds, true)) {
                    $validator->errors()->add("branch_prices.{$variantId}.{$branchId}", __('variants.errors.invalid_branch'));
                }
            }
        }
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
            'variant_options.*.name' => __('variants.option_name'),
            'variant_options.*.value' => __('variants.option_value'),
            'extra_variants.*.sku' => __('products.fields.sku'),
            'extra_variants.*.cost_price' => __('products.fields.cost_price'),
            'extra_variants.*.selling_price' => __('products.fields.selling_price'),
            'extra_variants.*.mrp' => __('products.fields.mrp'),
            'extra_variants.*.options.*.name' => __('variants.option_name'),
            'extra_variants.*.options.*.value' => __('variants.option_value'),
            'branch_prices.*.*' => __('variants.sections.branch_prices'),
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
            'extra_variants.*.sku.regex' => __('products.errors.sku_invalid'),
        ];
    }
}
