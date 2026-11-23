<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\ValidatesTranslations;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
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
        $editedCategory = $this->route('category');

        return [
            ...$this->translatableRules('name', true, 150),
            ...$this->translatableRules('description', false, 2000),
            ...$this->translatableRules('meta_title', false, 160),
            ...$this->translatableRules('meta_description', false, 320),
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                // Unique across deleted categories too, so a public URL is never reused.
                Rule::unique('categories', 'slug')->ignore($editedCategory instanceof Category ? $editedCategory->getKey() : null),
            ],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'is_active' => ['nullable', 'boolean'],
            // SVG is not allowed: it can carry scripts.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_image' => ['nullable', 'boolean'],
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
            ...$this->translatableAttributes('meta_title', __('catalog.meta_title')),
            ...$this->translatableAttributes('meta_description', __('catalog.meta_description')),
            'parent_id' => __('catalog.categories.parent'),
            'slug' => __('catalog.slug'),
            'sort_order' => __('catalog.sort_order'),
            'image' => __('catalog.image'),
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
