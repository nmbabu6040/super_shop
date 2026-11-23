<?php

declare(strict_types=1);

namespace App\Policies;

class BrandPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'brands';
    }
}
