<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Unit extends Model
{
    use HasFactory;
    use LogsActivity;
    use TracksAuthors;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_decimal',
        'decimal_places',
        'base_unit_id',
        'conversion_factor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'is_decimal' => 'boolean',
            'decimal_places' => 'integer',
            // Exact decimal string: quantities and factors never pass through floats.
            'conversion_factor' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(self::class, 'base_unit_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('unit')
            ->logOnly(['code', 'name', 'symbol', 'is_decimal', 'decimal_places', 'base_unit_id', 'conversion_factor', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
