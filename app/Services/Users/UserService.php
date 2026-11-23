<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffAccessChangedNotification;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\UserSessionService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff account management. Authorization (policies) is checked in the controllers; this
 * class enforces the rules that protect the system itself: no privilege escalation, no
 * self-lockout, never removing the last super administrator.
 */
final class UserService
{
    private const PROFILE_FIELDS = ['name', 'email', 'username', 'phone', 'preferred_locale'];

    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly StaffInvitationService $invitationService,
        private readonly UserSessionService $userSessionService,
        private readonly PasswordResetService $passwordResetService,
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {
    }

    /**
     * Creates a staff member in "pending" state and sends the invitation email.
     *
     * @param  array<string, mixed>  $data  Validated form data.
     */
    public function create(User $actor, array $data): User
    {
        $roleNames = $this->roleAssignmentService->assertAssignable($actor, $data['roles'] ?? []);
        $branchIds = $this->assertBranchesAssignable($actor, $data['branch_ids'] ?? []);
        $hasAllBranchAccess = $this->resolveAllBranchAccess($actor, (bool) ($data['has_all_branch_access'] ?? false));
        $this->assertHasBranchScope($hasAllBranchAccess, $branchIds);

        $user = DB::transaction(function () use ($actor, $data, $roleNames, $branchIds, $hasAllBranchAccess): User {
            $user = new User();
            $user->fill([
                ...Arr::only($data, self::PROFILE_FIELDS),
                // Unusable random password: the person sets their own through the invitation link.
                'password' => Str::random(64),
            ]);
            // Status and branch access are not mass assignable; they are set explicitly here.
            $user->forceFill([
                'status' => UserStatus::Pending,
                'has_all_branch_access' => $hasAllBranchAccess,
            ])->save();

            $user->syncRoles($roleNames);
            $user->branches()->sync($branchIds);

            $this->logChange($actor, $user, 'roles_assigned', [], $roleNames);
            $this->logChange($actor, $user, 'branches_assigned', [], $branchIds);

            return $user;
        });

        // After the transaction: a mail problem must not undo the account; "resend" is available.
        $this->invitationService->send($user, $actor);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data  Validated form data.
     */
    public function update(User $actor, User $user, array $data): User
    {
        if ($actor->is($user)) {
            throw BusinessRuleException::translated('users.cannot_edit_self');
        }

        $roleNames = $this->roleAssignmentService->assertAssignable($actor, $data['roles'] ?? []);
        $branchIds = $this->assertBranchesAssignable($actor, $data['branch_ids'] ?? []);

        // Only someone who has all-branch access can change it; for everyone else it stays as it is.
        $hasAllBranchAccess = $actor->has_all_branch_access
            ? (bool) ($data['has_all_branch_access'] ?? false)
            : (bool) $user->has_all_branch_access;

        $this->assertHasBranchScope($hasAllBranchAccess, $branchIds);

        if ($user->isSuperAdmin() && ! in_array(RoleName::SuperAdmin->value, $roleNames, true)) {
            $this->assertNotLastSuperAdmin($user);
        }

        $oldRoleNames = $user->getRoleNames()->sort()->values()->all();
        $oldBranchIds = $this->sortedBranchIds($user);
        $oldAllBranchAccess = (bool) $user->has_all_branch_access;
        $emailChanged = Str::lower((string) $data['email']) !== Str::lower($user->email);

        DB::transaction(function () use ($actor, $user, $data, $roleNames, $branchIds, $hasAllBranchAccess, $emailChanged, $oldRoleNames, $oldBranchIds): void {
            $user->fill(Arr::only($data, self::PROFILE_FIELDS));

            if ($emailChanged) {
                // The new address must be proven again.
                $user->email_verified_at = null;
            }

            $user->forceFill(['has_all_branch_access' => $hasAllBranchAccess])->save();
            $user->syncRoles($roleNames);
            $user->branches()->sync($branchIds);

            $this->logChange($actor, $user, 'roles_changed', $oldRoleNames, $roleNames);
            $this->logChange($actor, $user, 'branches_changed', $oldBranchIds, $branchIds);
        });

        if ($emailChanged && $user->status === UserStatus::Active) {
            $user->sendEmailVerificationNotification();
        }

        $accessChanged = $oldRoleNames !== $roleNames
            || $oldBranchIds !== $branchIds
            || $oldAllBranchAccess !== $hasAllBranchAccess;

        // A change of access is always reported to the person concerned (a security notice).
        if ($accessChanged && $user->status === UserStatus::Active) {
            $this->notificationDispatcher->send($user, new StaffAccessChangedNotification($actor->name));
        }

        return $user;
    }

    public function suspend(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw BusinessRuleException::translated('users.cannot_suspend_self');
        }

        if ($user->status !== UserStatus::Active) {
            throw BusinessRuleException::translated('users.not_active');
        }

        $this->assertNotLastSuperAdmin($user);

        $user->forceFill(['status' => UserStatus::Suspended])->save();

        // Signed out everywhere immediately (the "active" middleware also checks every request).
        $this->userSessionService->revokeAll($user);
    }

    public function reactivate(User $user): void
    {
        if ($user->status !== UserStatus::Suspended) {
            throw BusinessRuleException::translated('users.not_suspended');
        }

        $user->forceFill(['status' => UserStatus::Active])->save();
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw BusinessRuleException::translated('users.cannot_delete_self');
        }

        $this->assertNotLastSuperAdmin($user);

        if ($user->managedBranches()->exists()) {
            throw BusinessRuleException::translated('users.cannot_delete_branch_manager');
        }

        DB::transaction(function () use ($user): void {
            StaffInvitation::query()->where('user_id', $user->getKey())->delete();
            $user->delete();
        });

        $this->userSessionService->revokeAll($user);
    }

    /**
     * A restored account stays suspended until an administrator reactivates it on purpose.
     */
    public function restore(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Suspended]);
        $user->restore();
    }

    public function resendInvitation(User $actor, User $user): void
    {
        if ($user->status !== UserStatus::Pending) {
            throw BusinessRuleException::translated('users.invitation_not_pending');
        }

        $this->invitationService->send($user, $actor);
    }

    public function sendPasswordResetLink(User $user): void
    {
        if ($user->status !== UserStatus::Active) {
            throw BusinessRuleException::translated('users.not_active');
        }

        // An administrator never sets or sees a password: the person gets a reset link.
        $this->passwordResetService->sendResetLink($user->email);
    }

    /**
     * @param  list<int|string>  $requestedBranchIds
     * @return list<int>
     */
    private function assertBranchesAssignable(User $actor, array $requestedBranchIds): array
    {
        $requestedBranchIds = array_values(array_unique(array_map('intval', $requestedBranchIds)));

        $accessibleBranchIds = $actor->accessibleBranchesQuery()
            ->pluck('id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->all();

        if (array_diff($requestedBranchIds, $accessibleBranchIds) !== []) {
            throw BusinessRuleException::translated('users.branch_not_assignable');
        }

        sort($requestedBranchIds);

        return $requestedBranchIds;
    }

    private function resolveAllBranchAccess(User $actor, bool $requested): bool
    {
        if ($requested && ! $actor->has_all_branch_access) {
            throw BusinessRuleException::translated('users.cannot_grant_all_branches');
        }

        return $requested;
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function assertHasBranchScope(bool $hasAllBranchAccess, array $branchIds): void
    {
        if (! $hasAllBranchAccess && $branchIds === []) {
            throw BusinessRuleException::translated('users.branch_required');
        }
    }

    /**
     * Protects against locking everybody out of the system.
     */
    private function assertNotLastSuperAdmin(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $otherActiveSuperAdminExists = User::role(RoleName::SuperAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $otherActiveSuperAdminExists) {
            throw BusinessRuleException::translated('users.cannot_remove_last_super_admin');
        }
    }

    /**
     * @return list<int>
     */
    private function sortedBranchIds(User $user): array
    {
        return $user->branches()
            ->pluck('branches.id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Role and branch changes are not logged by Spatie, so they are written to the audit log here.
     *
     * @param  list<int|string>  $old
     * @param  list<int|string>  $new
     */
    private function logChange(User $actor, User $subject, string $event, array $old, array $new): void
    {
        if ($old === $new) {
            return;
        }

        activity('user')
            ->causedBy($actor)
            ->performedOn($subject)
            ->withProperties(['old' => $old, 'new' => $new])
            ->log($event);
    }
}
