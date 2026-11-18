<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WarehouseType;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Warehouse extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use TracksAuthors;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
        'is_sellable',
        'is_active',
        'default_for_branch_id',
        'address_line',
        'phone',
        'manager_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * A central warehouse belongs to the head office level, not to one branch.
     */
    public function isCentral(): bool
    {
        return $this->branch_id === null;
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true)->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly([
                'branch_id',
                'name',
                'code',
                'type',
                'is_sellable',
                'is_active',
                'default_for_branch_id',
                'manager_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
