<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Orders categories as a tree (parents first, children indented by depth).
 */
final class CategoryTree
{
    private const GUARD_DEPTH = 10;

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array{category: Category, depth: int}>
     */
    public function flatten(Collection $categories): array
    {
        $knownIds = $categories->pluck('id');

        // A category whose parent is not in the list (e.g. the parent is deleted) is shown as a root.
        $childrenByParent = $categories->groupBy(
            static fn (Category $category): int => $knownIds->contains($category->parent_id) ? (int) $category->parent_id : 0
        );

        $orderedRows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, $childrenByParent, &$orderedRows): void {
            if ($depth > self::GUARD_DEPTH) {
                return;
            }

            $siblings = $childrenByParent->get($parentId, collect())->sortBy(
                static fn (Category $category): string => sprintf('%05d', $category->sort_order) . '|' . mb_strtolower($category->name->get('en') ?? '')
            );

            foreach ($siblings as $category) {
                $orderedRows[] = ['category' => $category, 'depth' => $depth];
                $walk($category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $orderedRows;
    }
}
