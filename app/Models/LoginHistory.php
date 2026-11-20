<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only security trail of sign-in attempts. Old rows are pruned by a
 * scheduled job in the maintenance phase.
 */
class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'outcome',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => LoginOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
