<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Builds the audit log query for a user. Users without all-branch access only ever see
 * entries tagged with a branch they can access (untagged, older entries stay head-office only).
 */
final class AuditLogQuery
{
    /**
     * @param  array<string, mixed>  $filters  log_name, search, causer, from, to
     * @return Builder<Activity>
     */
    public function build(User $actor, array $filters = []): Builder
    {
        $query = Activity::query()->with('causer')->latest('id');

        if (! $actor->has_all_branch_access) {
            // An empty list matches nothing: a user without branches sees no entries.
            $query->whereIn('branch_id', $actor->branches()->pluck('branches.id')->all());
        }

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['search'])) {
            $searchTerm = (string) $filters['search'];

            $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                $searchQuery->where('description', 'like', "%{$searchTerm}%")
                    ->orWhere('log_name', 'like', "%{$searchTerm}%");
            });
        }

        if (! empty($filters['causer'])) {
            $causerTerm = (string) $filters['causer'];

            $query->whereHasMorph('causer', [User::class], function (Builder $causerQuery) use ($causerTerm): void {
                $causerQuery->where('name', 'like', "%{$causerTerm}%")
                    ->orWhere('email', 'like', "%{$causerTerm}%");
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }
}
