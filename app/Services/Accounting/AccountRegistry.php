<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\SystemAccount;
use App\Models\Account;
use RuntimeException;

/**
 * Finds the accounts the application posts to by their system key.
 */
final class AccountRegistry
{
    /** @var array<string, int> */
    private array $idsByKey = [];

    public function id(SystemAccount $systemAccount): int
    {
        if (! isset($this->idsByKey[$systemAccount->value])) {
            $accountId = Account::query()->where('system_key', $systemAccount->value)->value('id');

            if ($accountId === null) {
                throw new RuntimeException("The system account [{$systemAccount->value}] is missing. Run the AccountingSeeder.");
            }

            $this->idsByKey[$systemAccount->value] = (int) $accountId;
        }

        return $this->idsByKey[$systemAccount->value];
    }
}
