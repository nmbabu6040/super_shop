<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use Illuminate\Support\Arr;

/**
 * Chart of accounts rules: group accounts hold sub-accounts, only postable accounts hold
 * journal lines, a child has the type of its parent, and an account that was used is never
 * restructured or removed.
 */
final class AccountService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Account
    {
        $parent = $this->resolveParent($data['parent_id'] ?? null);
        $type = $parent !== null ? $parent->type : AccountType::from((string) $data['type']);

        return Account::query()->create([
            ...Arr::only($data, ['code', 'name']),
            'parent_id' => $parent?->getKey(),
            'type' => $type,
            'is_postable' => (bool) ($data['is_postable'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  The code never changes.
     */
    public function update(Account $account, array $data): Account
    {
        $hasLines = $account->lines()->exists();
        $hasChildren = $account->children()->exists();

        $parent = $this->resolveParent($data['parent_id'] ?? null);

        if ($parent !== null && ($parent->is($account) || in_array($parent->getKey(), $this->descendantIds($account), true))) {
            throw BusinessRuleException::translated('accounting.errors.parent_cycle');
        }

        $type = $parent !== null ? $parent->type : AccountType::from((string) ($data['type'] ?? $account->type->value));
        $isPostable = (bool) ($data['is_postable'] ?? $account->is_postable);
        $isActive = (bool) ($data['is_active'] ?? $account->is_active);

        if ($type !== $account->type && ($hasLines || $hasChildren || $account->isSystem())) {
            throw BusinessRuleException::translated('accounting.errors.type_locked');
        }

        if ($isPostable !== $account->is_postable) {
            if ($account->isSystem()) {
                throw BusinessRuleException::translated('accounting.errors.system_locked');
            }

            if (! $isPostable && $hasLines) {
                throw BusinessRuleException::translated('accounting.errors.has_lines');
            }

            if ($isPostable && $hasChildren) {
                throw BusinessRuleException::translated('accounting.errors.has_children');
            }
        }

        if ($account->isSystem() && ! $isActive) {
            throw BusinessRuleException::translated('accounting.errors.system_locked');
        }

        $account->fill([
            'name' => $data['name'],
            'parent_id' => $parent?->getKey(),
            'type' => $type,
            'is_postable' => $isPostable,
            'is_active' => $isActive,
        ])->save();

        return $account;
    }

    public function delete(Account $account): void
    {
        if ($account->isSystem()) {
            throw BusinessRuleException::translated('accounting.errors.system_locked');
        }

        if ($account->children()->exists()) {
            throw BusinessRuleException::translated('accounting.errors.has_children');
        }

        if ($account->lines()->exists()) {
            throw BusinessRuleException::translated('accounting.errors.has_lines');
        }

        $account->delete();
    }

    /**
     * @return list<int>
     */
    public function descendantIds(Account $account): array
    {
        $descendantIds = [];
        $currentLevelIds = [$account->getKey()];

        while ($currentLevelIds !== []) {
            $currentLevelIds = Account::query()
                ->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $descendantIds = [...$descendantIds, ...$currentLevelIds];
        }

        return $descendantIds;
    }

    /**
     * Only a group (non-postable) account can have sub-accounts.
     */
    private function resolveParent(int|string|null $parentId): ?Account
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $parent = Account::query()->find((int) $parentId);

        if ($parent === null) {
            throw BusinessRuleException::translated('accounting.errors.parent_missing');
        }

        if ($parent->is_postable) {
            throw BusinessRuleException::translated('accounting.errors.parent_must_be_group');
        }

        return $parent;
    }
}
