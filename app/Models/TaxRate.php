<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TaxRate extends Model
{
    use HasFactory;
    use LogsActivity;
    use TracksAuthors;

    protected $fillable = [
        'name',
        'rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            // Exact decimal string (percent), never a float.
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isDefault(): bool
    {
        return $this->default_marker !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tax_rate')
            ->logOnly(['name', 'rate', 'default_marker', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
