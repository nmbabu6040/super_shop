<?php

declare(strict_types=1);

namespace App\Policies;

class RolePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'roles';
    }
}
