<?php

declare(strict_types=1);

use App\Enums\LoginOutcome;
use App\Enums\RoleName;
use App\Models\LoginHistory;

beforeEach(function (): void {
    seedRbac();
});

it('shows the login page in both languages', function (): void {
    $this->get('/bn/login')->assertOk()->assertSee(__('auth.login_title', [], 'bn'));
    $this->get('/en/login')->assertOk()->assertSee(__('auth.login_title', [], 'en'));
});

it('signs a customer in with email, username or mobile number', function (string $identifier): void {
    $customer = createCustomer([
        'email' => 'rahim@example.com',
        'username' => 'rahim',
        'phone' => '01712345678',
    ]);

    $this->post('/bn/login', ['identifier' => $identifier, 'password' => STRONG_TEST_PASSWORD])
        ->assertRedirect(route('customer.account', ['locale' => 'bn']));

    $this->assertAuthenticatedAs($customer);
})->with([
    'email' => ['rahim@example.com'],
    'email in other case' => ['Rahim@Example.com'],
    'username' => ['rahim'],
    'local mobile number' => ['01712345678'],
    'mobile number with country code' => ['+880 1712-345678'],
]);

it('sends staff to the admin area after sign in', function (): void {
    createStaffWithRole(RoleName::BranchManager, ['email' => 'manager@example.com']);

    $this->post('/bn/login', ['identifier' => 'manager@example.com', 'password' => STRONG_TEST_PASSWORD])
        ->assertRedirect(route('admin.dashboard'));
});

it('rejects a wrong password with a generic message and records the attempt', function (): void {
    $customer = createCustomer(['email' => 'rahim@example.com']);

    $this->post('/bn/login', ['identifier' => 'rahim@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['identifier' => __('auth.failed', [], 'bn')]);

    $this->assertGuest();

    $history = LoginHistory::query()->where('outcome', LoginOutcome::FailedPassword->value)->first();

    expect($history)->not->toBeNull();
    expect($history->user_id)->toEqual($customer->id);
});

it('gives an unknown account exactly the same message and does not store the typed identifier', function (): void {
    $this->post('/bn/login', ['identifier' => 'nobody@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['identifier' => __('auth.failed', [], 'bn')]);

    $history = LoginHistory::query()->where('outcome', LoginOutcome::FailedUnknownAccount->value)->first();

    expect($history)->not->toBeNull();
    expect($history->user_id)->toBeNull();
    expect(json_encode($history->getAttributes()))->not->toContain('nobody@example.com');
});

it('locks an identifier out after five failed attempts, even for the right password', function (): void {
    createCustomer(['email' => 'locked@example.com']);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/bn/login', ['identifier' => 'locked@example.com', 'password' => 'wrong-password']);
    }

    $this->post('/bn/login', ['identifier' => 'locked@example.com', 'password' => STRONG_TEST_PASSWORD])
        ->assertSessionHasErrors('identifier');

    $this->assertGuest();
    expect(LoginHistory::query()->where('outcome', LoginOutcome::Throttled->value)->exists())->toBeTrue();
});

it('blocks a suspended account only after the correct password and records it', function (): void {
    createCustomer(['email' => 'suspended@example.com', 'status' => 'suspended']);

    $this->post('/bn/login', ['identifier' => 'suspended@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['identifier' => __('auth.failed', [], 'bn')]);

    $this->post('/bn/login', ['identifier' => 'suspended@example.com', 'password' => STRONG_TEST_PASSWORD])
        ->assertSessionHasErrors(['identifier' => __('auth.inactive', [], 'bn')]);

    $this->assertGuest();
    expect(LoginHistory::query()->where('outcome', LoginOutcome::BlockedInactive->value)->exists())->toBeTrue();
});

it('records the last login time and address', function (): void {
    $customer = createCustomer(['email' => 'rahim@example.com']);

    $this->post('/bn/login', ['identifier' => 'rahim@example.com', 'password' => STRONG_TEST_PASSWORD]);

    $freshCustomer = $customer->fresh();

    expect($freshCustomer->last_login_at)->not->toBeNull();
    expect($freshCustomer->last_login_ip)->toBe('127.0.0.1');
    expect(LoginHistory::query()->where('outcome', LoginOutcome::Success->value)->count())->toBe(1);
});

it('signs the user out and invalidates the session', function (): void {
    $customer = createCustomer();

    $this->actingAs($customer)
        ->post('/bn/logout')
        ->assertRedirect(route('frontend.home', ['locale' => 'bn']));

    $this->assertGuest();
});

it('sends guests who open protected pages to the login page in the current language', function (): void {
    $this->get('/admin')->assertRedirect(route('login', ['locale' => 'bn']));
    $this->get('/en/account')->assertRedirect(route('login', ['locale' => 'en']));
});

it('redirects signed-in users away from the login page', function (): void {
    $this->actingAs(createCustomer())
        ->get('/bn/login')
        ->assertRedirect(route('customer.account', ['locale' => 'bn']));
});
