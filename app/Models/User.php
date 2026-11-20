<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * SECURITY: "status" and "has_all_branch_access" are deliberately NOT mass assignable.
     * A request payload must never be able to grant branch access or change account status;
     * only service code may set them explicitly. Roles are assigned only through services too.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'gender',
        'address_line',
        'city',
        'district',
        'postal_code',
        'avatar_path',
        'preferred_locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'gender' => Gender::class,
            'has_all_branch_access' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * True for storefront customers: the user has the customer role and nothing else.
     * A user with no roles at all is NOT customer-only (they get no access, fail-closed).
     */
    public function isCustomerOnly(): bool
    {
        $roleNames = $this->getRoleNames();

        return $roleNames->isNotEmpty()
            && $roleNames->every(static fn (string $roleName): bool => $roleName === RoleName::Customer->value);
    }

    /**
     * Branch-level authorization primitive. Policies and the BranchContext service
     * build on this; never rely on frontend menu hiding.
     */
    public function canAccessBranch(Branch|int $branch): bool
    {
        if ($this->has_all_branch_access) {
            return true;
        }

        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $this->branches()->whereKey($branchId)->exists();
    }

    /**
     * Query of every branch this user may see.
     */
    public function accessibleBranchesQuery(): Builder
    {
        if ($this->has_all_branch_access) {
            return Branch::query();
        }

        return Branch::query()->whereHas(
            'users',
            fn (Builder $usersQuery): Builder => $usersQuery->where('users.id', $this->getKey())
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Password, remember token and last-login fields are intentionally not logged.
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly([
                'name',
                'username',
                'email',
                'phone',
                'status',
                'has_all_branch_access',
                'gender',
                'address_line',
                'city',
                'district',
                'postal_code',
                'preferred_locale',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
