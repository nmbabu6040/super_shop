<?php

declare(strict_types=1);

use App\Services\Auth\UserSessionService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;

beforeEach(function (): void {
    seedRbac();
});

it('shows the forgot password page', function (): void {
    $this->get('/bn/forgot-password')->assertOk()->assertSee(__('auth.forgot_title', [], 'bn'));
});

it('sends a reset link for an existing account', function (): void {
    Notification::fake();
    $customer = createCustomer(['email' => 'rahim@example.com']);

    $this->post('/bn/forgot-password', ['email' => 'Rahim@Example.com'])
        ->assertSessionHas('status', __('auth.reset_link_sent_generic', [], 'bn'));

    Notification::assertSentTo($customer, ResetPassword::class);
});

it('gives the same answer for an unknown email and sends nothing', function (): void {
    Notification::fake();

    $this->post('/bn/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', __('auth.reset_link_sent_generic', [], 'bn'));

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function (): void {
    $customer = createCustomer(['email' => 'rahim@example.com']);
    $token = PasswordBroker::createToken($customer);

    $this->post('/bn/reset-password', [
        'token' => $token,
        'email' => 'rahim@example.com',
        'password' => 'N3w!Passw0rd#2026',
        'password_confirmation' => 'N3w!Passw0rd#2026',
    ])->assertRedirect(route('login', ['locale' => 'bn']));

    expect(Hash::check('N3w!Passw0rd#2026', $customer->fresh()->password))->toBeTrue();
});

it('rejects a weak new password', function (): void {
    $customer = createCustomer(['email' => 'rahim@example.com']);
    $token = PasswordBroker::createToken($customer);

    $this->post('/bn/reset-password', [
        'token' => $token,
        'email' => 'rahim@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check(STRONG_TEST_PASSWORD, $customer->fresh()->password))->toBeTrue();
});

it('rejects an invalid token with the same message as an unknown email', function (): void {
    createCustomer(['email' => 'rahim@example.com']);

    $this->post('/bn/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'rahim@example.com',
        'password' => 'N3w!Passw0rd#2026',
        'password_confirmation' => 'N3w!Passw0rd#2026',
    ])->assertSessionHasErrors(['email' => __('passwords.token', [], 'bn')]);

    $this->post('/bn/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'nobody@example.com',
        'password' => 'N3w!Passw0rd#2026',
        'password_confirmation' => 'N3w!Passw0rd#2026',
    ])->assertSessionHasErrors(['email' => __('passwords.token', [], 'bn')]);
});

it('signs the user out of every device when sessions are stored in the database', function (): void {
    config(['session.driver' => 'database']);

    $customer = createCustomer();
    $otherUser = createCustomer();

    foreach ([[$customer, 'session-one'], [$customer, 'session-two'], [$otherUser, 'session-other']] as [$sessionOwner, $sessionId]) {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $sessionOwner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    app(UserSessionService::class)->revokeAll($customer);

    expect(DB::table('sessions')->where('user_id', $customer->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $otherUser->id)->count())->toBe(1);
});
