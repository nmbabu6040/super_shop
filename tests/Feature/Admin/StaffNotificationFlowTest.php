<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffAccessChangedNotification;
use App\Services\Users\StaffInvitationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRbac();
});

it('tells a staff member by email and in the app when their access changes', function (): void {
    Notification::fake();
    $branch = Branch::factory()->create();
    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branch);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.users.update', $cashier), [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'roles' => [RoleName::PosOperator->value],
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    Notification::assertSentTo(
        $cashier,
        StaffAccessChangedNotification::class,
        fn (StaffAccessChangedNotification $notification, array $channels): bool => $channels === ['mail', 'database']
    );
});

it('does not send an access notice when only the name changes', function (): void {
    Notification::fake();
    $branch = Branch::factory()->create();
    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branch);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.users.update', $cashier), [
            'name' => 'Renamed Cashier',
            'email' => $cashier->email,
            'roles' => [RoleName::Cashier->value],
            'branch_ids' => [$branch->id],
        ]);

    Notification::assertNotSentTo($cashier, StaffAccessChangedNotification::class);
});

it('stores the access notice in the app with the right link-free payload', function (): void {
    $branch = Branch::factory()->create();
    $cashier = createStaffWithRole(RoleName::Cashier);
    $cashier->branches()->attach($branch);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.users.update', $cashier), [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'roles' => [RoleName::PosOperator->value],
            'branch_ids' => [$branch->id],
        ]);

    $stored = $cashier->notifications()->firstOrFail();

    expect($stored->data['type'])->toBe('staff_access_changed');
    expect($stored->data['route'])->toBeNull();
    expect($stored->data['title_key'])->toBe('notifications.messages.staff_access_changed.title');
});

it('tells the inviter when the invited person activates their account', function (): void {
    $inviter = createHeadOfficeAdmin();
    $invitedUser = User::factory()->create(['status' => UserStatus::Pending->value, 'name' => 'Invited Person']);

    $plainToken = str_repeat('k', 64);

    StaffInvitation::query()->create([
        'user_id' => $invitedUser->id,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addDay(),
        'created_by' => $inviter->id,
    ]);

    app(StaffInvitationService::class)->accept($plainToken, 'Chosen!Passw0rd#2026');

    $stored = $inviter->notifications()->firstOrFail();

    expect($stored->data['type'])->toBe('staff_invitation_accepted');
    expect($stored->data['params'])->toBe(['name' => 'Invited Person']);
    expect($stored->data['route'])->toBe(['name' => 'admin.users.index']);
});

it('sends nothing when the invitation has no known inviter', function (): void {
    $invitedUser = User::factory()->create(['status' => UserStatus::Pending->value]);
    $plainToken = str_repeat('m', 64);

    StaffInvitation::query()->create([
        'user_id' => $invitedUser->id,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addDay(),
        'created_by' => null,
    ]);

    app(StaffInvitationService::class)->accept($plainToken, 'Chosen!Passw0rd#2026');

    expect(\Illuminate\Notifications\DatabaseNotification::query()->count())->toBe(0);
});
