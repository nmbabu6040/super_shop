<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Translatable;
use App\Enums\AccountType;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Account extends Model
{
    use HasFactory, LogsActivity, TracksAuthors;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'type',
        'is_postable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'type' => AccountType::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('account')
            ->logOnly(['code', 'name', 'type', 'parent_id', 'is_postable', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
