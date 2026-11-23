<?php

declare(strict_types=1);

namespace App\Policies;

class CategoryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'categories';
    }
}
