<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductMedicineDetail extends Model
{
    use LogsActivity;

    protected $fillable = [
        'product_id',
        'generic_name',
        'manufacturer',
        'drug_category',
        'registration_number',
        'requires_prescription',
        'is_controlled',
        'storage_conditions',
    ];

    protected function casts(): array
    {
        return [
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Prescription and controlled-drug flags are regulatory data: every change is audited.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('medicine_detail')
            ->logOnly(['product_id', 'generic_name', 'registration_number', 'requires_prescription', 'is_controlled'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
