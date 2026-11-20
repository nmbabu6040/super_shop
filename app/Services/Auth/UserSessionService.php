<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserSessionService
{
    /**
     * Signs the user out of every device. Works with the database session driver;
     * other drivers cannot be enumerated per user (the remember token is rotated separately).
     */
    public function revokeAll(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
