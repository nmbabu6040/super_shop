<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Customer',
        'email' => 'New.Customer@Example.com',
        'phone' => '+8801712345678',
        'password' => STRONG_TEST_PASSWORD,
        'password_confirmation' => STRONG_TEST_PASSWORD,
    ], $overrides);
}

it('shows the registration page', function (): void {
    $this->get('/bn/register')->assertOk()->assertSee(__('auth.register_title', [], 'bn'));
});

it('registers a customer, signs them in and sends one verification email', function (): void {
    Notification::fake();

    $this->post('/bn/register', registrationPayload())
        ->assertRedirect(route('verification.notice', ['locale' => 'bn']));

    $user = User::query()->where('email', 'new.customer@example.com')->firstOrFail();

    expect($user->hasRole(RoleName::Customer->value))->toBeTrue();
    expect($user->getRoleNames()->all())->toBe([RoleName::Customer->value]);
    expect($user->phone)->toBe('01712345678');
    expect($user->preferred_locale)->toBe('bn');
    expect($user->status)->toBe(UserStatus::Active);
    expect($user->has_all_branch_access)->toBeFalse();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

it('ignores any attempt to choose a role, status or branch access', function (): void {
    Notification::fake();

    $this->post('/bn/register', registrationPayload([
        'role' => RoleName::SuperAdmin->value,
        'roles' => [RoleName::SuperAdmin->value],
        'status' => 'suspended',
        'has_all_branch_access' => '1',
    ]));

    $user = User::query()->where('email', 'new.customer@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([RoleName::Customer->value]);
    expect($user->status)->toBe(UserStatus::Active);
    expect($user->has_all_branch_access)->toBeFalse();
});

it('rejects invalid registrations', function (array $overrides, string $invalidField): void {
    User::factory()->create(['email' => 'taken@example.com', 'phone' => '01811111111']);

    $this->post('/bn/register', registrationPayload($overrides))
        ->assertSessionHasErrors($invalidField);

    expect(User::query()->where('name', 'New Customer')->exists())->toBeFalse();
})->with([
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'duplicate phone' => [['phone' => '01811111111'], 'phone'],
    'invalid phone' => [['phone' => '12345'], 'phone'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatching confirmation' => [['password_confirmation' => 'Different!Passw0rd#2026'], 'password'],
    'missing name' => [['name' => ''], 'name'],
]);

it('allows registration without a phone number', function (): void {
    Notification::fake();

    $this->post('/en/register', registrationPayload(['phone' => '']))
        ->assertRedirect(route('verification.notice', ['locale' => 'en']));

    $user = User::query()->where('email', 'new.customer@example.com')->firstOrFail();

    expect($user->phone)->toBeNull();
    expect($user->preferred_locale)->toBe('en');
});
