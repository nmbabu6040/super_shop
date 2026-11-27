<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Weighted-average cost of a variant in a warehouse. Written only by the stock service.
 */
class StockCost extends Model
{
    protected $fillable = [
        'warehouse_id',
        'variant_id',
        'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'average_cost' => 'decimal:4',
        ];
    }
}
