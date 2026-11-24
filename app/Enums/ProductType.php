<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case General = 'general';
    case Medicine = 'medicine';

    public function label(): string
    {
        return __('products.types.' . $this->value);
    }
}
