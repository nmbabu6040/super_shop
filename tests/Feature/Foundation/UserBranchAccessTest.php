<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lets a user with all-branch access reach every branch', function (): void {
    $user = User::factory()->withAllBranchAccess()->create();
    $branch = Branch::factory()->create();

    expect($user->canAccessBranch($branch))->toBeTrue();
});

it('limits other users to their assigned branches', function (): void {
    $assignedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $user = User::factory()->create();
    $user->branches()->attach($assignedBranch);

    expect($user->canAccessBranch($assignedBranch))->toBeTrue();
    expect($user->canAccessBranch($otherBranch->id))->toBeFalse();
});

it('lists only the branches a user may access', function (): void {
    $assignedBranch = Branch::factory()->create();
    Branch::factory()->create();

    $user = User::factory()->create();
    $user->branches()->attach($assignedBranch);

    expect($user->accessibleBranchesQuery()->pluck('id')->all())->toEqual([$assignedBranch->id]);
});

it('cannot grant branch access or change status through mass assignment', function (): void {
    $user = User::query()->create([
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'a-strong-password',
        'has_all_branch_access' => true,
        'status' => 'suspended',
    ]);

    $freshUser = $user->fresh();

    expect($freshUser->has_all_branch_access)->toBeFalse();
    expect($freshUser->isActive())->toBeTrue();
});

it('hashes the password automatically', function (): void {
    $user = User::factory()->create(['password' => 'plain-text-password']);

    expect($user->password)->not->toBe('plain-text-password');
    expect(Hash::check('plain-text-password', $user->password))->toBeTrue();
});
