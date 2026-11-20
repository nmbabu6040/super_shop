<?php

declare(strict_types=1);

namespace App\Support\Branch;

use App\Exceptions\BranchAccessDeniedException;
use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Decides which branches the current actor may read and write.
 *
 * Rules (see Phase 4a notes):
 *  - Guests and customer-only users are not restricted here (the storefront shows
 *    stock of every branch); their access is limited by route permissions and
 *    "own data" policies instead.
 *  - Users with has_all_branch_access are unrestricted but may focus on one branch.
 *  - Every other authenticated user is restricted to the branches assigned to them.
 *    A user with no roles or no branches sees nothing (fail-closed).
 *  - Console commands and queued jobs get NO exemption: cross-branch work must say so
 *    explicitly with runUnrestricted() or runForBranch().
 *
 * Registered as a "scoped" binding, so every request and queue job gets a fresh instance.
 */
final class BranchContext
{
    public const SESSION_KEY = 'active_branch_id';

    private int $unrestrictedDepth = 0;

    private ?int $forcedBranchId = null;

    private ?int $selectedBranchId = null;

    /** @var array<int, list<int>> */
    private array $accessibleBranchIdsByUser = [];

    /** @var array<int, bool> */
    private array $customerOnlyByUser = [];

    /**
     * Branch ids the actor is limited to, or null when not restricted.
     *
     * @return list<int>|null
     */
    public function restrictedBranchIds(): ?array
    {
        if ($this->unrestrictedDepth > 0) {
            return null;
        }

        $user = $this->authenticatedUser();

        if ($user === null || $user->has_all_branch_access || $this->isCustomerOnly($user)) {
            return null;
        }

        return $this->accessibleBranchIdsFor($user);
    }

    /**
     * The branch the user is currently working in, or null for "all branches".
     */
    public function activeBranchId(): ?int
    {
        if ($this->forcedBranchId !== null) {
            return $this->forcedBranchId;
        }

        $user = $this->authenticatedUser();

        if ($user === null || $this->isCustomerOnly($user)) {
            return null;
        }

        $restrictedBranchIds = $this->restrictedBranchIds();
        $selectedBranchId = $this->selectedBranchId ?? $this->branchIdFromSession();

        if (
            $selectedBranchId !== null
            && ($restrictedBranchIds === null || in_array($selectedBranchId, $restrictedBranchIds, true))
        ) {
            return $selectedBranchId;
        }

        // Restricted staff always work inside one branch: default to their first one.
        if ($restrictedBranchIds !== null && $restrictedBranchIds !== []) {
            return $restrictedBranchIds[0];
        }

        return null;
    }

    /**
     * Branch ids the global BranchScope filters by. Null means "do not filter".
     * An empty list means "match nothing" (fail-closed).
     *
     * @return list<int>|null
     */
    public function scopeBranchIds(): ?array
    {
        if ($this->unrestrictedDepth > 0) {
            return null;
        }

        if ($this->forcedBranchId !== null) {
            return [$this->forcedBranchId];
        }

        $restrictedBranchIds = $this->restrictedBranchIds();
        $activeBranchId = $this->activeBranchId();

        if ($restrictedBranchIds === null) {
            return $activeBranchId === null ? null : [$activeBranchId];
        }

        return $activeBranchId !== null ? [$activeBranchId] : $restrictedBranchIds;
    }

    /**
     * Throws unless the actor may write a record that belongs to $branchId.
     * A null branch id (shared record) may only be written by unrestricted actors.
     */
    public function assertCanWriteToBranch(?int $branchId): void
    {
        $restrictedBranchIds = $this->restrictedBranchIds();

        if ($restrictedBranchIds === null) {
            return;
        }

        if ($branchId !== null && in_array($branchId, $restrictedBranchIds, true)) {
            return;
        }

        Log::warning('Blocked a write to a branch the user cannot access.', [
            'user_id' => $this->authenticatedUser()?->getKey(),
            'branch_id' => $branchId,
        ]);

        throw BranchAccessDeniedException::forBranch();
    }

    /**
     * Choose the branch the user wants to work in (stored in the session).
     * Passing null clears the choice, so the default applies again.
     */
    public function setActiveBranch(?int $branchId): void
    {
        if ($this->authenticatedUser() === null) {
            throw BranchAccessDeniedException::forBranch();
        }

        if ($branchId !== null) {
            $restrictedBranchIds = $this->restrictedBranchIds();

            $isAllowed = $restrictedBranchIds === null
                ? Branch::query()->whereKey($branchId)->exists()
                : in_array($branchId, $restrictedBranchIds, true);

            if (! $isAllowed) {
                throw BranchAccessDeniedException::forBranch();
            }
        }

        $this->selectedBranchId = $branchId;

        $request = request();

        if ($request->hasSession()) {
            $branchId === null
                ? $request->session()->forget(self::SESSION_KEY)
                : $request->session()->put(self::SESSION_KEY, $branchId);
        }
    }

    /**
     * Run code without any branch restriction (system jobs, reports built to span branches).
     * Use sparingly: this deliberately bypasses branch isolation.
     */
    public function runUnrestricted(Closure $callback): mixed
    {
        $this->unrestrictedDepth++;

        try {
            return $callback();
        } finally {
            $this->unrestrictedDepth--;
        }
    }

    /**
     * Run code as if the given branch were the active one (queued jobs, schedulers).
     * A restricted user cannot run code for a branch they have no access to.
     */
    public function runForBranch(int $branchId, Closure $callback): mixed
    {
        $this->assertCanWriteToBranch($branchId);

        $previousBranchId = $this->forcedBranchId;
        $this->forcedBranchId = $branchId;

        try {
            return $callback();
        } finally {
            $this->forcedBranchId = $previousBranchId;
        }
    }

    /**
     * Forget everything remembered during this request (mainly useful in tests).
     */
    public function flush(): void
    {
        $this->accessibleBranchIdsByUser = [];
        $this->customerOnlyByUser = [];
        $this->selectedBranchId = null;
    }

    private function authenticatedUser(): ?User
    {
        $authenticatedUser = Auth::user();

        return $authenticatedUser instanceof User ? $authenticatedUser : null;
    }

    private function isCustomerOnly(User $user): bool
    {
        return $this->customerOnlyByUser[$user->getKey()] ??= $user->isCustomerOnly();
    }

    /**
     * @return list<int>
     */
    private function accessibleBranchIdsFor(User $user): array
    {
        return $this->accessibleBranchIdsByUser[$user->getKey()] ??= $user->branches()
            ->pluck('branches.id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->sort()
            ->values()
            ->all();
    }

    private function branchIdFromSession(): ?int
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $storedBranchId = $request->session()->get(self::SESSION_KEY);

        return is_numeric($storedBranchId) ? (int) $storedBranchId : null;
    }
}
