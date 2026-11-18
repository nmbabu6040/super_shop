<?php

declare(strict_types=1);

namespace App\Enums;

enum WarehouseType: string
{
    case ShopFloor = 'shop_floor';
    case Storage = 'storage';
    case Central = 'central';

    public function label(): string
    {
        return __('enums.warehouse_type.' . $this->value);
    }
}
