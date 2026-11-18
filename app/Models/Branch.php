<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BranchStatus;
use App\Models\Concerns\TracksAuthors;
use App\Observers\BranchObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ObservedBy(BranchObserver::class)]
class Branch extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use TracksAuthors;

    protected $fillable = [
        'name',
        'code',
        'is_head_office',
        'status',
        'address_line',
        'city',
        'district',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'email',
        'manager_id',
        'opening_date',
        'opening_balance',
        'working_hours',
        'logo_path',
        'tax_registration_number',
    ];

    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'is_head_office' => 'boolean',
            'opening_date' => 'date',
            // "decimal:2" keeps money as an exact string instead of a float.
            'opening_balance' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'working_hours' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * The shop-floor warehouse that sales (POS and online) deduct stock from.
     */
    public function defaultWarehouse(): HasOne
    {
        return $this->hasOne(Warehouse::class, 'default_for_branch_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopeOperating(Builder $query): Builder
    {
        return $query->where('status', BranchStatus::Active->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'code',
                'email',
                'phone',
                'address',
                'opening_balance',
                'tax_registration_number',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
