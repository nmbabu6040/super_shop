<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductVariant extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use TracksAuthors;

    protected $fillable = [
        'product_id',
        'sku',
        'options',
        'cost_price',
        'selling_price',
        'mrp',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            // Money as exact decimal strings: prices never pass through floats.
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function primaryBarcode(): HasOne
    {
        return $this->hasOne(ProductBarcode::class, 'variant_id')->where('is_primary', true);
    }

    public function isDefault(): bool
    {
        return $this->default_for_product_id !== null;
    }

    /**
     * Prices are audit-relevant: every change of SKU or price is written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product_variant')
            ->logOnly(['product_id', 'sku', 'cost_price', 'selling_price', 'mrp', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
