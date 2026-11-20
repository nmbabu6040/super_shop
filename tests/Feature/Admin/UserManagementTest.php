<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validStaffPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Staff',
        'email' => 'staff@example.com',
        'username' => 'newstaff',
        'phone' => '01712345678',
        'preferred_locale' => 'bn',
        'roles' => [RoleName::Cashier->value],
        'branch_ids' => [],
        'has_all_branch_access' => '0',
    ], $overrides);
}

/**
 * A branch manager who may also create and edit staff (not part of the default role).
 */
function createUserManagerFor(Branch $branch): User
{
    $manager = createBranchStaff($branch);
    $manager->givePermissionTo(['users.create', 'users.update', 'users.delete']);

    return $manager;
}

it('lets the head office create staff, who start pending and are invited by email', function (): void {
    Notification::fake();
    $branch = Branch::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/users', validStaffPayload(['branch_ids' => [$branch->id]]))
        ->assertRedirect(route('admin.users.index'));

    $newUser = User::query()->where('email', 'staff@example.com')->firstOrFail();

    expect($newUser->status)->toBe(UserStatus::Pending);
    expect($newUser->getRoleNames()->all())->toBe([RoleName::Cashier->value]);
    expect($newUser->branches()->pluck('branches.id')->all())->toEqual([$branch->id]);
    expect($newUser->has_all_branch_access)->toBeFalse();
    expect(StaffInvitation::query()->where('user_id', $newUser->id)->exists())->toBeTrue();

    Notification::assertSentTo($newUser, StaffInvitationNotification::class);
});

it('rejects invalid staff data', function (array $overrides, string $invalidField): void {
    $branch = Branch::factory()->create();
    User::factory()->create(['email' => 'taken@example.com', 'username' => 'taken', 'phone' => '01811111111']);

    $payload = validStaffPayload(array_merge(['branch_ids' => [$branch->id]], $overrides));

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/users', $payload)
        ->assertSessionHasErrors($invalidField);

    expect(User::query()->where('name', 'New Staff')->exists())->toBeFalse();
})->with([
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'duplicate username' => [['username' => 'taken'], 'username'],
    'duplicate phone' => [['phone' => '01811111111'], 'phone'],
    'invalid username' => [['username' => 'Bad Name!'], 'username'],
    'invalid phone' => [['phone' => '12345'], 'phone'],
    'no roles' => [['roles' => []], 'roles'],
    'unknown role' => [['roles' => ['made-up-role']], 'roles.0'],
    'no branch and no all-branch access' => [['branch_ids' => []], 'branch_ids'],
]);

it('stops a head office admin from handing out the super-admin role', function (): void {
    $branch = Branch::factory()->create();

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/users', validStaffPayload([
            'roles' => [RoleName::SuperAdmin->value],
            'branch_ids' => [$branch->id],
        ]))
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse();
});

it('lets a super administrator create another super administrator with all-branch access', function (): void {
    Notification::fake();
    $superAdmin = createStaffWithRole(RoleName::SuperAdmin, ['has_all_branch_access' => true]);

    $this->actingAs($superAdmin)
        ->post('/admin/users', validStaffPayload([
            'roles' => [RoleName::SuperAdmin->value],
            'has_all_branch_access' => '1',
        ]))
        ->assertRedirect(route('admin.users.index'));

    $newUser = User::query()->where('email', 'staff@example.com')->firstOrFail();

    expect($newUser->isSuperAdmin())->toBeTrue();
    expect($newUser->has_all_branch_access)->toBeTrue();
});

it('stops a branch manager from escalating privileges or leaving their branch', function (): void {
    Notification::fake();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = createUserManagerFor($ownBranch);

    // A role with more access than the manager has.
    $this->actingAs($manager)
        ->post('/admin/users', validStaffPayload([
            'roles' => [RoleName::HeadOfficeAdmin->value],
            'branch_ids' => [$ownBranch->id],
        ]))
        ->assertSessionHas('error');

    // A branch the manager cannot access.
    $this->actingAs($manager)
        ->post('/admin/users', validStaffPayload(['branch_ids' => [$otherBranch->id]]))
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse();

    // Handing out all-branch access is silently ignored for someone who does not have it.
    $this->actingAs($manager)
        ->post('/admin/users', validStaffPayload([
            'branch_ids' => [$ownBranch->id],
            'has_all_branch_access' => '1',
        ]))
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('email', 'staff@example.com')->firstOrFail()->has_all_branch_access)->toBeFalse();
});

it('lets a branch manager edit staff of their own branch but not higher-ranking users', function (): void {
    $branch = Branch::factory()->create();
    $manager = createUserManagerFor($branch);

    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branch);

    $headOfficeAdmin = createStaffWithRole(RoleName::HeadOfficeAdmin, ['has_all_branch_access' => true]);
    $headOfficeAdmin->branches()->attach($branch);

    $this->actingAs($manager)->get(route('admin.users.edit', $cashier))->assertOk();
    $this->actingAs($manager)->get(route('admin.users.edit', $headOfficeAdmin))->assertForbidden();
});

it('does not let anyone edit their own roles through the user screen', function (): void {
    $headOfficeAdmin = createHeadOfficeAdmin();

    $this->actingAs($headOfficeAdmin)
        ->get(route('admin.users.edit', $headOfficeAdmin))
        ->assertForbidden();
});

it('refuses a super administrator who tries to change their own roles', function (): void {
    $branch = Branch::factory()->create();
    $superAdmin = createStaffWithRole(RoleName::SuperAdmin, ['has_all_branch_access' => true]);

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $superAdmin), validStaffPayload([
            'email' => $superAdmin->email,
            'roles' => [RoleName::Cashier->value],
            'branch_ids' => [$branch->id],
        ]))
        ->assertSessionHas('error');

    expect($superAdmin->fresh()->isSuperAdmin())->toBeTrue();
});

it('updates roles and branches and writes them to the audit log', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branchA);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.users.update', $cashier), validStaffPayload([
            'email' => $cashier->email,
            'username' => null,
            'phone' => null,
            'roles' => [RoleName::PosOperator->value],
            'branch_ids' => [$branchB->id],
        ]))
        ->assertRedirect(route('admin.users.index'));

    $freshCashier = $cashier->fresh();

    expect($freshCashier->getRoleNames()->all())->toBe([RoleName::PosOperator->value]);
    expect($freshCashier->branches()->pluck('branches.id')->all())->toEqual([$branchB->id]);

    $auditEvents = DB::table('activity_log')->pluck('description')->all();

    expect($auditEvents)->toContain('roles_changed');
    expect($auditEvents)->toContain('branches_changed');
});

it('makes the user verify again when the email address changes', function (): void {
    Notification::fake();
    $branch = Branch::factory()->create();
    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branch);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.users.update', $cashier), validStaffPayload([
            'email' => 'changed@example.com',
            'username' => null,
            'phone' => null,
            'roles' => [RoleName::Cashier->value],
            'branch_ids' => [$branch->id],
        ]));

    $freshCashier = $cashier->fresh();

    expect($freshCashier->email)->toBe('changed@example.com');
    expect($freshCashier->email_verified_at)->toBeNull();
    Notification::assertSentTo($freshCashier, VerifyEmail::class);
});

it('suspends a user, signs them out everywhere and lets an admin reactivate them', function (): void {
    config(['session.driver' => 'database']);
    $cashier = createStaffWithRole(RoleName::Cashier);
    $admin = createHeadOfficeAdmin();

    DB::table('sessions')->insert([
        'id' => 'cashier-session',
        'user_id' => $cashier->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $cashier))
        ->assertRedirect(route('admin.users.index'));

    expect($cashier->fresh()->status)->toBe(UserStatus::Suspended);
    expect(DB::table('sessions')->where('user_id', $cashier->id)->count())->toBe(0);

    $this->actingAs($admin)
        ->post(route('admin.users.reactivate', $cashier))
        ->assertRedirect(route('admin.users.index'));

    expect($cashier->fresh()->status)->toBe(UserStatus::Active);
});

it('only allows actions that fit the account status', function (): void {
    $admin = createHeadOfficeAdmin();
    $activeUser = createStaffWithRole(RoleName::Cashier);
    $pendingUser = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);

    $this->actingAs($admin)->post(route('admin.users.reactivate', $activeUser))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.invitation', $activeUser))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.suspend', $pendingUser))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.password-reset', $pendingUser))->assertForbidden();
});

it('resends an invitation to a pending user', function (): void {
    Notification::fake();
    $pendingUser = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);

    $this->actingAs(createHeadOfficeAdmin())
        ->post(route('admin.users.invitation', $pendingUser))
        ->assertRedirect(route('admin.users.index'));

    Notification::assertSentTo($pendingUser, StaffInvitationNotification::class);
});

it('sends a password reset link instead of ever exposing a password', function (): void {
    Notification::fake();
    $cashier = createStaffWithRole(RoleName::Cashier);

    $this->actingAs(createHeadOfficeAdmin())
        ->post(route('admin.users.password-reset', $cashier))
        ->assertRedirect(route('admin.users.index'));

    Notification::assertSentTo($cashier, ResetPassword::class);
});

it('deletes staff, protects branch managers, and restores accounts as suspended', function (): void {
    $admin = createHeadOfficeAdmin();

    $managerUser = createStaffWithRole(RoleName::Cashier);
    Branch::factory()->create(['manager_id' => $managerUser->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $managerUser))
        ->assertSessionHas('error');

    expect(User::query()->whereKey($managerUser->id)->exists())->toBeTrue();

    $plainUser = createStaffWithRole(RoleName::Cashier);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $plainUser))
        ->assertRedirect(route('admin.users.index'));

    expect(User::withTrashed()->find($plainUser->id)->trashed())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.users.restore', $plainUser))
        ->assertRedirect(route('admin.users.index'));

    $restoredUser = User::query()->find($plainUser->id);

    expect($restoredUser)->not->toBeNull();
    expect($restoredUser->status)->toBe(UserStatus::Suspended);
});

it('keeps customers out of the staff list and out of reach', function (): void {
    $customer = createCustomer(['name' => 'Some Customer']);
    $cashier = createStaffWithRole(RoleName::Cashier, ['name' => 'Some Cashier']);

    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertViewHas('staffUsers', fn ($staffUsers): bool => $staffUsers->pluck('id')->contains($cashier->id)
            && ! $staffUsers->pluck('id')->contains($customer->id));

    $this->actingAs($admin)->get(route('admin.users.edit', $customer))->assertForbidden();
});

it('shows a branch manager only the staff of their own branches', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = createUserManagerFor($ownBranch);
    $manager->givePermissionTo('users.view');

    $ownStaff = createStaffWithRole(RoleName::Cashier);
    $ownStaff->branches()->attach($ownBranch);

    $foreignStaff = createStaffWithRole(RoleName::Cashier);
    $foreignStaff->branches()->attach($otherBranch);

    $this->actingAs($manager)
        ->get('/admin/users')
        ->assertOk()
        ->assertViewHas('staffUsers', fn ($staffUsers): bool => $staffUsers->pluck('id')->contains($ownStaff->id)
            && ! $staffUsers->pluck('id')->contains($foreignStaff->id));
});

it('forbids users without the users permission', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/users')->assertForbidden();
});
