<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by / updated_by from the authenticated user.
 * Console jobs and seeders (no authenticated user) leave both NULL.
 */
trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(static function (Model $model): void {
            $authenticatedUserId = Auth::id();

            if ($authenticatedUserId !== null) {
                $model->created_by ??= $authenticatedUserId;
                $model->updated_by ??= $authenticatedUserId;
            }
        });

        static::updating(static function (Model $model): void {
            $authenticatedUserId = Auth::id();

            if ($authenticatedUserId !== null && ! $model->isDirty('updated_by')) {
                $model->updated_by = $authenticatedUserId;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
