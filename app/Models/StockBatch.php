<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use TracksAuthors;

    protected $fillable = [
        'variant_id',
        'batch_number',
        'expiry_date',
        'manufactured_on',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'manufactured_on' => 'date',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'batch_id');
    }

    /**
     * A batch is valid through its expiry date and expired from the next day on.
     */
    public function isExpired(?CarbonInterface $on = null): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->startOfDay()->lt(($on ?? now())->startOfDay());
    }
}
