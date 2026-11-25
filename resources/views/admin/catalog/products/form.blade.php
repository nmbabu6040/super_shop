@php
    $isEditing = $product !== null;
    $pageTitle = $isEditing ? __('products.edit_title') : __('products.create_title');
    $variant = $product?->defaultVariant;
    $medicine = $product?->medicineDetail;
    $initialType = old('product_type', $product?->product_type->value ?? 'general');

    $emptyOption = [['name' => '', 'value' => '']];
    $optionsToRows = fn (?array $options): array => collect($options ?? [])
        ->map(fn ($value, $name): array => ['name' => (string) $name, 'value' => (string) $value])
        ->values()
        ->all() ?: $emptyOption;

    $oldDefaultOptions = old('variant_options');
    $initialDefaultOptions = is_array($oldDefaultOptions) && $oldDefaultOptions !== []
        ? array_values($oldDefaultOptions)
        : $optionsToRows($variant?->options);

    $oldExtras = old('extra_variants');
    $initialExtras = is_array($oldExtras)
        ? collect($oldExtras)->values()->map(fn (array $row, int $index): array => [
            'uid' => $index + 1,
            'id' => $row['id'] ?? null,
            'sku' => $row['sku'] ?? '',
            'options' => array_values($row['options'] ?? []) ?: $emptyOption,
            'cost_price' => $row['cost_price'] ?? '0.00',
            'selling_price' => $row['selling_price'] ?? '0.00',
            'mrp' => $row['mrp'] ?? '',
            'barcodes' => $row['barcodes'] ?? '',
            'generate_barcode' => ! empty($row['generate_barcode']),
            'is_active' => (string) ($row['is_active'] ?? '1'),
        ])->all()
        : ($product
            ? $product->variants->whereNull('default_for_product_id')->values()->map(fn ($extraVariant, int $index): array => [
                'uid' => $index + 1,
                'id' => $extraVariant->id,
                'sku' => $extraVariant->sku,
                'options' => $optionsToRows($extraVariant->options),
                'cost_price' => $extraVariant->cost_price,
                'selling_price' => $extraVariant->selling_price,
                'mrp' => $extraVariant->mrp ?? '',
                'barcodes' => $extraVariant->barcodes->pluck('barcode')->implode("\n"),
                'generate_barcode' => false,
                'is_active' => $extraVariant->is_active ? '1' : '0',
            ])->all()
            : []);

    $defaultExtraBarcodes = old('extra_barcodes', $variant ? $variant->barcodes->where('is_primary', false)->pluck('barcode')->implode("\n") : '');
    $fieldClasses = 'w-full rounded-xl border border-line bg-surface-raised px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/20';
@endphp
<x-layouts.admin :title="$pageTitle"
                 :breadcrumbs="[[__('admin.menu.dashboard'), route('admin.dashboard')], [__('products.title'), route('admin.products.index')], [$pageTitle, null]]">
    <x-admin.page-header :title="$pageTitle" />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100" role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $errorMessage)
                    <li>{{ $errorMessage }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEditing ? route('admin.products.update', $product) : route('admin.products.store') }}"
          enctype="multipart/form-data"
          x-data="{
              busy: false,
              productType: @js($initialType),
              defaultOptions: @js($initialDefaultOptions),
              extras: @js($initialExtras),
              nextUid: {{ count($initialExtras) + 1 }},
              addExtra() {
                  this.extras.push({ uid: this.nextUid++, id: null, sku: '', options: [{ name: '', value: '' }], cost_price: '0.00', selling_price: '0.00', mrp: '', barcodes: '', generate_barcode: false, is_active: '1' });
              },
              removeExtra(index) { this.extras.splice(index, 1); },
              addOption(list) { if (list.length < 5) { list.push({ name: '', value: '' }); } },
              removeOption(list, index) { list.splice(index, 1); },
          }"
          x-on:submit="busy = true"
          x-on:pageshow.window="busy = false"
          class="space-y-6"
          novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.basics') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$product?->name" :required="true" />
                <x-admin.translatable-input name="description" :label="__('catalog.description')" :values="$product?->description" :multiline="true" />
                <x-admin.select name="category_id" :label="__('products.fields.category')" :options="$categoryOptions"
                                :selected="$product?->category_id" :placeholder="__('admin.common.select')" :required="true" />
                <x-admin.select name="brand_id" :label="__('products.fields.brand')" :options="$brandOptions"
                                :selected="$product?->brand_id" :placeholder="__('admin.common.none')" />
                @if ($isEditing)
                    <div>
                        <p class="text-sm font-medium">{{ __('products.fields.unit') }}</p>
                        <p class="mt-1 text-sm">{{ $product->unit->name }} ({{ $product->unit->symbol }})</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('products.unit_locked') }}</p>
                    </div>
                @else
                    <x-admin.select name="unit_id" :label="__('products.fields.unit')" :options="$unitOptions"
                                    :placeholder="__('admin.common.select')" :required="true" :help="__('products.unit_help')" />
                @endif
                <x-admin.select name="tax_rate_id" :label="__('products.fields.tax_rate')" :options="$taxRateOptions"
                                :selected="$product ? $product->tax_rate_id : $defaultTaxRateId"
                                :placeholder="__('products.no_tax')" />
                <x-admin.select name="product_type" :label="__('products.fields.product_type')" :options="$typeOptions"
                                :selected="$initialType" :required="true" x-model="productType" />
                <x-admin.input name="slug" :label="__('catalog.slug')" :value="$product?->slug" :help="__('catalog.slug_help')" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.pricing') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="sku" :label="__('products.fields.sku')" :value="$variant?->sku"
                               :help="__('products.sku_help')" style="text-transform: uppercase" />
                <x-admin.input name="cost_price" type="number" step="0.01" min="0" :label="__('products.fields.cost_price')"
                               :value="$variant?->cost_price ?? '0.00'" :required="true" />
                <x-admin.input name="selling_price" type="number" step="0.01" min="0" :label="__('products.fields.selling_price')"
                               :value="$variant?->selling_price ?? '0.00'" :required="true" />
                <x-admin.input name="mrp" type="number" step="0.01" min="0" :label="__('products.fields.mrp')"
                               :value="$variant?->mrp" :help="__('products.mrp_help')" />
                <x-admin.input name="barcode" :label="__('products.fields.barcode')" :value="$variant?->primaryBarcode?->barcode"
                               :help="__('products.barcode_help')" />
                <div class="flex items-end pb-2">
                    <x-admin.checkbox name="generate_barcode" :label="__('products.fields.generate_barcode')" :checked="false" />
                </div>
                <div class="sm:col-span-3">
                    <label for="extra_barcodes" class="mb-1.5 block text-sm font-medium">{{ __('variants.extra_barcodes') }}</label>
                    <textarea id="extra_barcodes" name="extra_barcodes" rows="2" class="{{ $fieldClasses }}">{{ $defaultExtraBarcodes }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="text-lg font-semibold">{{ __('variants.sections.extra') }}</h2>
            <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('variants.extra_help') }}</p>

            {{-- Attributes of the first (default) variant: only needed, and only submitted, when other variants exist. --}}
            <fieldset class="mb-4 rounded-xl border border-line p-4"
                      x-show="extras.length > 0" x-cloak
                      x-bind:disabled="extras.length === 0">
                <legend class="px-2 text-sm font-semibold">{{ __('variants.default_options') }}</legend>
                <p class="mb-2 text-xs text-ink-muted">{{ __('variants.default_options_help') }}</p>
                <template x-for="(option, optionIndex) in defaultOptions" x-bind:key="optionIndex">
                    <div class="mb-2 flex gap-2">
                        <input type="text" class="{{ $fieldClasses }}" x-model="option.name"
                               x-bind:name="`variant_options[${optionIndex}][name]`"
                               placeholder="{{ __('variants.option_name') }}">
                        <input type="text" class="{{ $fieldClasses }}" x-model="option.value"
                               x-bind:name="`variant_options[${optionIndex}][value]`"
                               placeholder="{{ __('variants.option_value') }}">
                        <button type="button" class="btn btn-ghost" x-on:click="removeOption(defaultOptions, optionIndex)"
                                x-show="defaultOptions.length > 1" aria-label="{{ __('variants.remove_option') }}">
                            <x-ui.icon name="x" class="size-4" />
                        </button>
                    </div>
                </template>
                <button type="button" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300"
                        x-on:click="addOption(defaultOptions)">{{ __('variants.add_option') }}</button>
            </fieldset>

            <div class="space-y-4">
                <template x-for="(extra, index) in extras" x-bind:key="extra.uid">
                    <div class="rounded-xl border border-line p-4">
                        <input type="hidden" x-bind:name="`extra_variants[${index}][id]`" x-bind:value="extra.id" x-bind:disabled="! extra.id">

                        <div class="grid gap-3 sm:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium">{{ __('products.fields.sku') }}</label>
                                <input type="text" class="{{ $fieldClasses }}" style="text-transform: uppercase"
                                       x-model="extra.sku" x-bind:name="`extra_variants[${index}][sku]`">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium">{{ __('products.fields.cost_price') }}</label>
                                <input type="number" step="0.01" min="0" class="{{ $fieldClasses }}"
                                       x-model="extra.cost_price" x-bind:name="`extra_variants[${index}][cost_price]`" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium">{{ __('products.fields.selling_price') }}</label>
                                <input type="number" step="0.01" min="0" class="{{ $fieldClasses }}"
                                       x-model="extra.selling_price" x-bind:name="`extra_variants[${index}][selling_price]`" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium">{{ __('products.fields.mrp') }}</label>
                                <input type="number" step="0.01" min="0" class="{{ $fieldClasses }}"
                                       x-model="extra.mrp" x-bind:name="`extra_variants[${index}][mrp]`">
                            </div>
                        </div>

                        <div class="mt-3">
                            <p class="mb-1 text-xs font-medium">{{ __('variants.sections.options') }}</p>
                            <template x-for="(option, optionIndex) in extra.options" x-bind:key="optionIndex">
                                <div class="mb-2 flex gap-2">
                                    <input type="text" class="{{ $fieldClasses }}" x-model="option.name"
                                           x-bind:name="`extra_variants[${index}][options][${optionIndex}][name]`"
                                           placeholder="{{ __('variants.option_name') }}">
                                    <input type="text" class="{{ $fieldClasses }}" x-model="option.value"
                                           x-bind:name="`extra_variants[${index}][options][${optionIndex}][value]`"
                                           placeholder="{{ __('variants.option_value') }}">
                                    <button type="button" class="btn btn-ghost" x-on:click="removeOption(extra.options, optionIndex)"
                                            x-show="extra.options.length > 1" aria-label="{{ __('variants.remove_option') }}">
                                        <x-ui.icon name="x" class="size-4" />
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300"
                                    x-on:click="addOption(extra.options)">{{ __('variants.add_option') }}</button>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium">{{ __('variants.barcodes') }}</label>
                                <textarea rows="2" class="{{ $fieldClasses }}" x-model="extra.barcodes"
                                          x-bind:name="`extra_variants[${index}][barcodes]`"></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="mb-1 block text-xs font-medium">{{ __('admin.common.status') }}</label>
                                <select class="{{ $fieldClasses }}" x-model="extra.is_active"
                                        x-bind:name="`extra_variants[${index}][is_active]`">
                                    <option value="1">{{ __('admin.common.active') }}</option>
                                    <option value="0">{{ __('admin.common.inactive') }}</option>
                                </select>
                                <label class="flex items-center gap-2 text-xs">
                                    <input type="checkbox" value="1" class="size-4 accent-brand-600" x-model="extra.generate_barcode"
                                           x-bind:name="`extra_variants[${index}][generate_barcode]`">
                                    {{ __('products.fields.generate_barcode') }}
                                </label>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-ghost text-red-600" x-on:click="removeExtra(index)">
                                <x-ui.icon name="trash" class="size-4" />
                                {{ __('variants.remove_variant') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" class="btn btn-ghost mt-4" x-on:click="addExtra()">
                <x-ui.icon name="plus" class="size-4" />
                {{ __('variants.add_variant') }}
            </button>
        </section>

        @if ($isEditing && $accessibleBranches->isNotEmpty())
            <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
                <h2 class="text-lg font-semibold">{{ __('variants.sections.branch_prices') }}</h2>
                <p class="mb-4 mt-1 text-sm text-ink-muted">{{ __('variants.branch_prices_help') }}</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-ink-muted">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('variants.variant') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('variants.base_price') }}</th>
                                @foreach ($accessibleBranches as $branch)
                                    <th class="px-3 py-2 text-start">{{ $branch->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product->variants as $branchPriceVariant)
                                <tr class="border-t border-line">
                                    <td class="px-3 py-2">
                                        <div class="font-mono text-xs">{{ $branchPriceVariant->sku }}</div>
                                        @if ($branchPriceVariant->options)
                                            <div class="text-xs text-ink-muted">{{ implode(' / ', $branchPriceVariant->options) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-end">{{ \App\Support\Money\MoneyFormatter::format($branchPriceVariant->selling_price) }}</td>
                                    @foreach ($accessibleBranches as $branch)
                                        <td class="px-3 py-2">
                                            <input type="number" step="0.01" min="0"
                                                   name="branch_prices[{{ $branchPriceVariant->id }}][{{ $branch->id }}]"
                                                   value="{{ old('branch_prices.'.$branchPriceVariant->id.'.'.$branch->id, $branchPrices[$branchPriceVariant->id][$branch->id] ?? '') }}"
                                                   class="w-28 rounded-xl border border-line bg-surface-raised px-2 py-1.5 text-sm">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.stock') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="reorder_level" type="number" step="0.0001" min="0" :label="__('products.fields.reorder_level')" :value="$product?->reorder_level ?? '0'" />
                <x-admin.input name="min_stock" type="number" step="0.0001" min="0" :label="__('products.fields.min_stock')" :value="$product?->min_stock ?? '0'" />
                <x-admin.input name="max_stock" type="number" step="0.0001" min="0" :label="__('products.fields.max_stock')" :value="$product?->max_stock" />
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <x-admin.checkbox name="track_batches" :label="__('products.fields.track_batches')" :checked="$product?->track_batches ?? false" />
                <x-admin.checkbox name="track_expiry" :label="__('products.fields.track_expiry')" :checked="$product?->track_expiry ?? false" />
                <x-admin.checkbox name="track_serial" :label="__('products.fields.track_serial')" :checked="$product?->track_serial ?? false" />
            </div>
            <p class="mt-2 text-xs text-ink-muted" x-show="productType === 'medicine'" x-cloak>{{ __('products.medicine_forced_note') }}</p>
        </section>

        {{-- Medicine details: only submitted when the product type is "medicine". --}}
        <fieldset class="rounded-card border border-line bg-surface-raised p-6 shadow-card"
                  x-show="productType === 'medicine'" x-cloak
                  x-bind:disabled="productType !== 'medicine'">
            <legend class="px-2 text-lg font-semibold">{{ __('products.sections.medicine') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="medicine[generic_name]" :label="__('products.fields.generic_name')"
                               :value="old('medicine.generic_name', $medicine?->generic_name)" />
                <x-admin.input name="medicine[manufacturer]" :label="__('products.fields.manufacturer')"
                               :value="old('medicine.manufacturer', $medicine?->manufacturer)" />
                <x-admin.input name="medicine[drug_category]" :label="__('products.fields.drug_category')"
                               :value="old('medicine.drug_category', $medicine?->drug_category)" />
                <x-admin.input name="medicine[registration_number]" :label="__('products.fields.registration_number')"
                               :value="old('medicine.registration_number', $medicine?->registration_number)" />
                <x-admin.input name="medicine[storage_conditions]" :label="__('products.fields.storage_conditions')"
                               :value="old('medicine.storage_conditions', $medicine?->storage_conditions)" />
                <div class="flex flex-col justify-end gap-2 pb-2">
                    <x-admin.checkbox name="medicine[requires_prescription]" :label="__('products.fields.requires_prescription')"
                                      :checked="old('medicine.requires_prescription', $medicine?->requires_prescription ?? true)" />
                    <x-admin.checkbox name="medicine[is_controlled]" :label="__('products.fields.is_controlled')"
                                      :checked="old('medicine.is_controlled', $medicine?->is_controlled ?? false)" />
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-muted">{{ __('products.prescription_warning') }}</p>
        </fieldset>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.images') }}</h2>

            @if ($isEditing && $product->images->isNotEmpty())
                <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($product->images as $image)
                        <div class="rounded-xl border border-line p-2">
                            <img src="{{ \App\Support\Media\PublicImageStore::url($image->path) }}" alt=""
                                 class="aspect-square w-full rounded-lg object-cover" loading="lazy">
                            <label class="mt-2 flex items-center gap-2 text-xs">
                                <input type="radio" name="primary_image_id" value="{{ $image->id }}"
                                       class="size-4 accent-brand-600" @checked($image->isPrimary())>
                                {{ __('products.fields.primary_image') }}
                            </label>
                            <label class="mt-1 flex items-center gap-2 text-xs text-red-600">
                                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="size-4 accent-red-600">
                                {{ __('products.fields.remove_image') }}
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <label for="images" class="mb-1.5 block text-sm font-medium">{{ __('products.fields.images') }}</label>
            <input id="images" name="images[]" type="file" multiple accept="image/png,image/jpeg,image/webp" class="text-sm">
            <p class="mt-1 text-xs text-ink-muted">{{ __('products.images_help', ['max' => \App\Services\Catalog\ProductService::MAX_IMAGES]) }}</p>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.visibility') }}</h2>
            <div class="flex flex-wrap gap-6">
                <x-admin.checkbox name="is_active" :label="__('admin.common.active')" :checked="$product?->is_active ?? true" />
                <x-admin.checkbox name="is_sold_online" :label="__('products.fields.is_sold_online')" :checked="$product?->is_sold_online ?? true" />
                <x-admin.checkbox name="is_featured" :label="__('products.fields.is_featured')" :checked="$product?->is_featured ?? false" />
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary" x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
