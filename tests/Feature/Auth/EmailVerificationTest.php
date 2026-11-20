<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    seedRbac();
});

function verificationUrlFor(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'locale' => 'bn',
        'id' => $user->getKey(),
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

it('shows the verification notice to an unverified user', function (): void {
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->get('/bn/email/verify')
        ->assertOk()
        ->assertSee(__('auth.verify_title', [], 'bn'));
});

it('verifies the email through the signed link', function (): void {
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->get(verificationUrlFor($customer))
        ->assertRedirect(route('customer.account', ['locale' => 'bn']));

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('refuses a link with a wrong hash', function (): void {
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->get(verificationUrlFor($customer, 'not-the-right-hash'))
        ->assertForbidden();

    expect($customer->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses a link that is not signed', function (): void {
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->get('/bn/email/verify/' . $customer->id . '/' . sha1($customer->email))
        ->assertForbidden();
});

it('does not let one user verify another user\'s address', function (): void {
    $victim = createCustomer(['email_verified_at' => null]);
    $attacker = createCustomer(['email_verified_at' => null]);

    $this->actingAs($attacker)
        ->get(verificationUrlFor($victim))
        ->assertForbidden();

    expect($victim->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email to an unverified user', function (): void {
    Notification::fake();
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->post('/bn/email/verification-notification')
        ->assertSessionHas('status', __('auth.verification_link_sent', [], 'bn'));

    Notification::assertSentTo($customer, VerifyEmail::class);
});

it('does not resend to an already verified user', function (): void {
    Notification::fake();

    $this->actingAs(createCustomer())->post('/bn/email/verification-notification');

    Notification::assertNothingSent();
});

it('keeps unverified customers away from the account page', function (): void {
    $customer = createCustomer(['email_verified_at' => null]);

    $this->actingAs($customer)
        ->get('/bn/account')
        ->assertRedirect(route('verification.notice', ['locale' => 'bn']));
});
