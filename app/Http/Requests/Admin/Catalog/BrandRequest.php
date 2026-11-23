<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand
            ? $this->user()->can('update', $brand)
            : $this->user()->can('create', Brand::class);
    }

    protected function prepareForValidation(): void
    {
        $normalizedInput = [];

        if (is_string($this->input('slug'))) {
            $normalizedInput['slug'] = strtolower(trim($this->input('slug')));
        }

        if ($this->has('is_active')) {
            $normalizedInput['is_active'] = $this->boolean('is_active');
        }

        $this->merge($normalizedInput);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedBrand = $this->route('brand');

        return [
            ...$this->translatableRules('name', true, 150),
            ...$this->translatableRules('description', false, 2000),
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('brands', 'slug')->ignore($editedBrand instanceof Brand ? $editedBrand->getKey() : null),
            ],
            // Only http/https links: no javascript: or data: URLs.
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
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
            'website_url' => __('catalog.brands.website'),
            'sort_order' => __('catalog.sort_order'),
            'logo' => __('catalog.brands.logo'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('catalog.invalid_slug'),
        ];
    }
}
