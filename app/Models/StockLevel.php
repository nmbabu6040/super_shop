<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity on hand. Branch-bound staff see the stock of their own branches and of the central
 * warehouse (branch NULL), but may only change stock of their own branches.
 */
class StockLevel extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'warehouse_id',
        'branch_id',
        'variant_id',
        'batch_id',
        'batch_key',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public static function allowsSharedBranchRecords(): bool
    {
        return true;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }
}
