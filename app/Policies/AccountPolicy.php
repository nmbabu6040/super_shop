<?php

declare(strict_types=1);

namespace App\Policies;

class AccountPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'accounts';
    }
}
