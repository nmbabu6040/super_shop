<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Branch-specific selling price. BelongsToBranch limits what branch-bound staff can read and
 * write to their own branches.
 */
class VariantBranchPrice extends Model
{
    use BelongsToBranch;
    use LogsActivity;
    use TracksAuthors;

    protected $fillable = [
        'variant_id',
        'branch_id',
        'selling_price',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('branch_price')
            ->logOnly(['variant_id', 'branch_id', 'selling_price'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
