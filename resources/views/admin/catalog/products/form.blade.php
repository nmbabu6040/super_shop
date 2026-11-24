@php
    $isEditing = $product !== null;
    $pageTitle = $isEditing ? __('products.edit_title') : __('products.create_title');
    $variant = $product?->defaultVariant;
    $medicine = $product?->medicineDetail;
    $initialType = old('product_type', $product?->product_type->value ?? 'general');
@endphp
<x-layouts.admin :title="$pageTitle" :breadcrumbs="[
    [__('admin.menu.dashboard'), route('admin.dashboard')],
    [__('products.title'), route('admin.products.index')],
    [$pageTitle, null],
]">
    <x-admin.page-header :title="$pageTitle" />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100"
            role="alert">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $errorMessage)
                    <li>{{ $errorMessage }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
        action="{{ $isEditing ? route('admin.products.update', $product) : route('admin.products.store') }}"
        enctype="multipart/form-data" x-data="{ busy: false, productType: @js($initialType) }" x-on:submit="busy = true" x-on:pageshow.window="busy = false"
        class="space-y-6" novalidate>
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.basics') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.translatable-input name="name" :label="__('catalog.name')" :values="$product?->name" :required="true" />
                <x-admin.translatable-input name="description" :label="__('catalog.description')" :values="$product?->description" :multiline="true" />
                <x-admin.select name="category_id" :label="__('products.fields.category')" :options="$categoryOptions" :selected="$product?->category_id"
                    :placeholder="__('admin.common.select')" :required="true" />
                <x-admin.select name="brand_id" :label="__('products.fields.brand')" :options="$brandOptions" :selected="$product?->brand_id"
                    :placeholder="__('admin.common.none')" />
                @if ($isEditing)
                    <div>
                        <p class="text-sm font-medium">{{ __('products.fields.unit') }}</p>
                        <p class="mt-1 text-sm">{{ $product->unit->name }} ({{ $product->unit->symbol }})</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('products.unit_locked') }}</p>
                    </div>
                @else
                    <x-admin.select name="unit_id" :label="__('products.fields.unit')" :options="$unitOptions" :placeholder="__('admin.common.select')"
                        :required="true" :help="__('products.unit_help')" />
                @endif
                <x-admin.select name="tax_rate_id" :label="__('products.fields.tax_rate')" :options="$taxRateOptions" :selected="$product ? $product->tax_rate_id : $defaultTaxRateId"
                    :placeholder="__('products.no_tax')" />
                <x-admin.select name="product_type" :label="__('products.fields.product_type')" :options="$typeOptions" :selected="$initialType"
                    :required="true" x-model="productType" />
                <x-admin.input name="slug" :label="__('catalog.slug')" :value="$product?->slug" :help="__('catalog.slug_help')" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.pricing') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="sku" :label="__('products.fields.sku')" :value="$variant?->sku" :help="__('products.sku_help')"
                    style="text-transform: uppercase" />
                <x-admin.input name="cost_price" type="number" step="0.01" min="0" :label="__('products.fields.cost_price')"
                    :value="$variant?->cost_price ?? '0.00'" :required="true" />
                <x-admin.input name="selling_price" type="number" step="0.01" min="0" :label="__('products.fields.selling_price')"
                    :value="$variant?->selling_price ?? '0.00'" :required="true" />
                <x-admin.input name="mrp" type="number" step="0.01" min="0" :label="__('products.fields.mrp')"
                    :value="$variant?->mrp" :help="__('products.mrp_help')" />
                <x-admin.input name="barcode" :label="__('products.fields.barcode')" :value="$variant?->primaryBarcode?->barcode" :help="__('products.barcode_help')" />
                <div class="flex items-end pb-2">
                    <x-admin.checkbox name="generate_barcode" :label="__('products.fields.generate_barcode')" :checked="false" />
                </div>
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface-raised p-6 shadow-card">
            <h2 class="mb-4 text-lg font-semibold">{{ __('products.sections.stock') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="reorder_level" type="number" step="0.0001" min="0" :label="__('products.fields.reorder_level')"
                    :value="$product?->reorder_level ?? '0'" />
                <x-admin.input name="min_stock" type="number" step="0.0001" min="0" :label="__('products.fields.min_stock')"
                    :value="$product?->min_stock ?? '0'" />
                <x-admin.input name="max_stock" type="number" step="0.0001" min="0" :label="__('products.fields.max_stock')"
                    :value="$product?->max_stock" />
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <x-admin.checkbox name="track_batches" :label="__('products.fields.track_batches')" :checked="$product?->track_batches ?? false" />
                <x-admin.checkbox name="track_expiry" :label="__('products.fields.track_expiry')" :checked="$product?->track_expiry ?? false" />
                <x-admin.checkbox name="track_serial" :label="__('products.fields.track_serial')" :checked="$product?->track_serial ?? false" />
            </div>
            <p class="mt-2 text-xs text-ink-muted" x-show="productType === 'medicine'" x-cloak>
                {{ __('products.medicine_forced_note') }}</p>
        </section>

        {{-- Medicine details: only submitted when the product type is "medicine". --}}
        <fieldset class="rounded-card border border-line bg-surface-raised p-6 shadow-card"
            x-show="productType === 'medicine'" x-cloak x-bind:disabled="productType !== 'medicine'">
            <legend class="px-2 text-lg font-semibold">{{ __('products.sections.medicine') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="medicine[generic_name]" :label="__('products.fields.generic_name')" :value="old('medicine.generic_name', $medicine?->generic_name)" />
                <x-admin.input name="medicine[manufacturer]" :label="__('products.fields.manufacturer')" :value="old('medicine.manufacturer', $medicine?->manufacturer)" />
                <x-admin.input name="medicine[drug_category]" :label="__('products.fields.drug_category')" :value="old('medicine.drug_category', $medicine?->drug_category)" />
                <x-admin.input name="medicine[registration_number]" :label="__('products.fields.registration_number')" :value="old('medicine.registration_number', $medicine?->registration_number)" />
                <x-admin.input name="medicine[storage_conditions]" :label="__('products.fields.storage_conditions')" :value="old('medicine.storage_conditions', $medicine?->storage_conditions)" />
                <div class="flex flex-col justify-end gap-2 pb-2">
                    <x-admin.checkbox name="medicine[requires_prescription]" :label="__('products.fields.requires_prescription')" :checked="old('medicine.requires_prescription', $medicine?->requires_prescription ?? true)" />
                    <x-admin.checkbox name="medicine[is_controlled]" :label="__('products.fields.is_controlled')" :checked="old('medicine.is_controlled', $medicine?->is_controlled ?? false)" />
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
                                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}"
                                    class="size-4 accent-red-600">
                                {{ __('products.fields.remove_image') }}
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <label for="images" class="mb-1.5 block text-sm font-medium">{{ __('products.fields.images') }}</label>
            <input id="images" name="images[]" type="file" multiple accept="image/png,image/jpeg,image/webp"
                class="text-sm">
            <p class="mt-1 text-xs text-ink-muted">
                {{ __('products.images_help', ['max' => \App\Services\Catalog\ProductService::MAX_IMAGES]) }}</p>
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
            <button type="submit" class="btn btn-primary"
                x-bind:disabled="busy">{{ __('admin.common.save') }}</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
