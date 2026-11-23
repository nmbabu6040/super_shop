<?php

declare(strict_types=1);

namespace App\Policies;

class UnitPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'units';
    }
}
