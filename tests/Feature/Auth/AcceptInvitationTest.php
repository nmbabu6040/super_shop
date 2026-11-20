<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Users\StaffInvitationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRbac();
});

/**
 * Sends an invitation and returns the plain token from the emailed link.
 */
function issueInvitationToken(User $user): string
{
    Notification::fake();

    app(StaffInvitationService::class)->send($user);

    $token = null;

    Notification::assertSentTo($user, StaffInvitationNotification::class, function (StaffInvitationNotification $notification) use (&$token): bool {
        $token = basename((string) parse_url($notification->acceptUrl, PHP_URL_PATH));

        return true;
    });

    return (string) $token;
}

it('stores only a hash of the invitation token', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $token = issueInvitationToken($user);

    $invitation = StaffInvitation::query()->where('user_id', $user->id)->firstOrFail();

    expect($invitation->getRawOriginal('token_hash'))->not->toBe($token);
    expect($invitation->getRawOriginal('token_hash'))->toBe(hash('sha256', $token));
});

it('shows the activation form for a valid link', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending', 'name' => 'Invited Person']);
    $token = issueInvitationToken($user);

    $this->get('/bn/accept-invitation/' . $token)
        ->assertOk()
        ->assertSee('Invited Person');
});

it('activates the account, verifies the email and lets the person sign in', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, [
        'status' => 'pending',
        'email' => 'invited@example.com',
        'email_verified_at' => null,
    ]);
    $token = issueInvitationToken($user);

    $this->post('/bn/accept-invitation', [
        'token' => $token,
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ])->assertRedirect(route('login', ['locale' => 'bn']));

    $freshUser = $user->fresh();

    expect($freshUser->status)->toBe(UserStatus::Active);
    expect($freshUser->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('Chosen!Passw0rd#2026', $freshUser->password))->toBeTrue();
    expect(StaffInvitation::query()->where('user_id', $user->id)->exists())->toBeFalse();

    $this->post('/bn/login', ['identifier' => 'invited@example.com', 'password' => 'Chosen!Passw0rd#2026'])
        ->assertRedirect(route('admin.dashboard'));
});

it('cannot be used twice', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $token = issueInvitationToken($user);

    $payload = [
        'token' => $token,
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ];

    $this->post('/bn/accept-invitation', $payload);
    $this->post('/bn/accept-invitation', array_merge($payload, [
        'password' => 'Another!Passw0rd#2026',
        'password_confirmation' => 'Another!Passw0rd#2026',
    ]))->assertSessionHasErrors('password');

    expect(Hash::check('Chosen!Passw0rd#2026', $user->fresh()->password))->toBeTrue();
});

it('expires after seven days', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $token = issueInvitationToken($user);

    $this->travel(8)->days();

    $this->post('/bn/accept-invitation', [
        'token' => $token,
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ])->assertSessionHasErrors('password');

    expect($user->fresh()->status)->toBe(UserStatus::Pending);
});

it('is invalidated when a newer invitation is sent', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $oldToken = issueInvitationToken($user);
    $newToken = issueInvitationToken($user);

    expect($newToken)->not->toBe($oldToken);

    $this->post('/bn/accept-invitation', [
        'token' => $oldToken,
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ])->assertSessionHasErrors('password');
});

it('stops working once the account is no longer pending', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $token = issueInvitationToken($user);

    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->post('/bn/accept-invitation', [
        'token' => $token,
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ])->assertSessionHasErrors('password');
});

it('rejects a weak password and an unknown token', function (): void {
    $user = createStaffWithRole(RoleName::Cashier, ['status' => 'pending']);
    $token = issueInvitationToken($user);

    $this->post('/bn/accept-invitation', [
        'token' => $token,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->post('/bn/accept-invitation', [
        'token' => str_repeat('a', 64),
        'password' => 'Chosen!Passw0rd#2026',
        'password_confirmation' => 'Chosen!Passw0rd#2026',
    ])->assertSessionHasErrors('password');

    $this->get('/bn/accept-invitation/' . str_repeat('b', 64))
        ->assertOk()
        ->assertSee(__('invitation.invalid', [], 'bn'));
});
