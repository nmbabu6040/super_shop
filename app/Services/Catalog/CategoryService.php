<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Support\Media\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Category rules: a tree of at most three levels, no cycles, safe deletion.
 */
final class CategoryService
{
    public const MAX_DEPTH = 3;

    private const IMAGE_DIRECTORY = 'categories';

    /**
     * @param  array<string, mixed>  $data  Validated form data (without the image file).
     */
    public function create(array $data, ?UploadedFile $image = null): Category
    {
        $this->assertValidParent($data['parent_id'] ?? null, null);

        return DB::transaction(function () use ($data, $image): Category {
            $category = new Category();
            $category->fill([
                ...Arr::except($data, ['slug', 'remove_image']),
                'slug' => $this->resolveSlug($data),
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($image !== null) {
                $category->image_path = PublicImageStore::store($image, self::IMAGE_DIRECTORY);
            }

            $category->save();

            return $category;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data, ?UploadedFile $newImage = null, bool $removeImage = false): Category
    {
        $this->assertValidParent($data['parent_id'] ?? null, $category);

        $previousImagePath = $category->image_path;
        $imageIsChanging = $newImage !== null || ($removeImage && $previousImagePath !== null);

        DB::transaction(function () use ($category, $data, $newImage, $removeImage): void {
            $category->fill(Arr::except($data, ['slug', 'remove_image']));

            if (! empty($data['slug'])) {
                $category->slug = $data['slug'];
            }

            if ($newImage !== null) {
                $category->image_path = PublicImageStore::store($newImage, self::IMAGE_DIRECTORY);
            } elseif ($removeImage) {
                $category->image_path = null;
            }

            $category->save();
        });

        // The old file is removed only after the database change succeeded.
        if ($imageIsChanging) {
            PublicImageStore::delete($previousImagePath);
        }

        return $category;
    }

    public function delete(Category $category): void
    {
        if (Category::query()->where('parent_id', $category->getKey())->exists()) {
            throw BusinessRuleException::translated('catalog.categories.has_children');
        }

        // Deleted products still point to their category, so they count as well.
        if (Product::withTrashed()->where('category_id', $category->getKey())->exists()) {
            throw BusinessRuleException::translated('products.in_use.category');
        }

        $category->delete();
    }

    public function restore(Category $category): void
    {
        if ($category->parent_id !== null && Category::onlyTrashed()->whereKey($category->parent_id)->exists()) {
            throw BusinessRuleException::translated('catalog.categories.parent_deleted');
        }

        $category->restore();
    }

    /**
     * @return list<int> Ids of every category below the given one (deleted ones included).
     */
    public function descendantIds(Category $category): array
    {
        $descendantIds = [];
        $currentLevelIds = [$category->getKey()];

        while ($currentLevelIds !== []) {
            $currentLevelIds = Category::withTrashed()
                ->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $descendantIds = [...$descendantIds, ...$currentLevelIds];
        }

        return $descendantIds;
    }

    /**
     * The parent must exist, must not be the category itself or one of its descendants (a cycle),
     * and the whole subtree must still fit into MAX_DEPTH levels.
     */
    private function assertValidParent(int|string|null $parentId, ?Category $editing): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        $parent = Category::query()->find((int) $parentId);

        if ($parent === null) {
            throw BusinessRuleException::translated('catalog.categories.parent_missing');
        }

        if (
            $editing !== null
            && ($parent->getKey() === $editing->getKey() || in_array($parent->getKey(), $this->descendantIds($editing), true))
        ) {
            throw BusinessRuleException::translated('catalog.categories.parent_cycle');
        }

        // The moved category itself is one level; its own descendants add their height.
        $levelsBelowParent = 1 + ($editing !== null ? $this->subtreeHeight($editing) : 0);

        if ($this->depthOf($parent) + $levelsBelowParent > self::MAX_DEPTH) {
            throw BusinessRuleException::translated('catalog.categories.too_deep', ['max' => self::MAX_DEPTH]);
        }
    }

    /**
     * Level of a category: a root is level 1.
     */
    private function depthOf(Category $category): int
    {
        $depth = 1;
        $current = $category;

        while ($current->parent_id !== null && $depth < 10) {
            $current = Category::withTrashed()->find($current->parent_id);

            if ($current === null) {
                break;
            }

            $depth++;
        }

        return $depth;
    }

    /**
     * Number of levels below the category (0 for a leaf).
     */
    private function subtreeHeight(Category $category): int
    {
        $height = 0;
        $currentLevelIds = [$category->getKey()];

        while (true) {
            $currentLevelIds = Category::withTrashed()
                ->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->all();

            if ($currentLevelIds === []) {
                return $height;
            }

            $height++;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSlug(array $data): string
    {
        $baseSlug = ! empty($data['slug'])
            ? (string) $data['slug']
            : (Str::slug((string) ($data['name']['en'] ?? '')) ?: 'category');

        $candidate = $baseSlug;
        $suffix = 2;

        // Unique across deleted categories too.
        while (Category::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $suffix++;
        }

        return $candidate;
    }
}
