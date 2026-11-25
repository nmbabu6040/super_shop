<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * Orders accounts as a tree (parents first, children indented by depth, siblings by code).
 */
final class AccountTree
{
    private const GUARD_DEPTH = 10;

    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<array{account: Account, depth: int}>
     */
    public function flatten(Collection $accounts): array
    {
        $knownIds = $accounts->pluck('id');

        $childrenByParent = $accounts->groupBy(
            static fn(Account $account): int => $knownIds->contains($account->parent_id) ? (int) $account->parent_id : 0
        );

        $orderedRows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, $childrenByParent, &$orderedRows): void {
            if ($depth > self::GUARD_DEPTH) {
                return;
            }

            foreach ($childrenByParent->get($parentId, collect())->sortBy('code') as $account) {
                $orderedRows[] = ['account' => $account, 'depth' => $depth];
                $walk($account->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $orderedRows;
    }
}
