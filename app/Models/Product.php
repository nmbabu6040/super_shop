<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Translatable;
use App\Enums\ProductType;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use TracksAuthors;

    protected $fillable = [
        'category_id',
        'brand_id',
        'unit_id',
        'tax_rate_id',
        'product_type',
        'name',
        'description',
        'slug',
        'track_batches',
        'track_expiry',
        'track_serial',
        'reorder_level',
        'min_stock',
        'max_stock',
        'is_active',
        'is_featured',
        'is_sold_online',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'name' => Translatable::class,
            'description' => Translatable::class,
            'track_batches' => 'boolean',
            'track_expiry' => 'boolean',
            'track_serial' => 'boolean',
            'reorder_level' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_sold_online' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class, 'default_for_product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'primary_for_product_id');
    }

    public function medicineDetail(): HasOne
    {
        return $this->hasOne(ProductMedicineDetail::class);
    }

    public function isMedicine(): bool
    {
        return $this->product_type === ProductType::Medicine;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product')
            ->logOnly([
                'name',
                'slug',
                'category_id',
                'brand_id',
                'unit_id',
                'tax_rate_id',
                'product_type',
                'track_batches',
                'track_expiry',
                'is_active',
                'is_sold_online',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
