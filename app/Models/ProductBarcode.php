<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BarcodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    protected $fillable = [
        'variant_id',
        'barcode',
        'type',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type' => BarcodeType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}
