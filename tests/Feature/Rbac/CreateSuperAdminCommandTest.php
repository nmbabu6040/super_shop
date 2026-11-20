<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

it('creates a super administrator with all-branch access and a verified email', function (): void {
    $this->artisan('shop:create-super-admin', ['--name' => 'Shop Owner', '--email' => 'owner@example.com'])
        ->expectsQuestion('Password', 'Str0ng!Passw0rd#2026')
        ->expectsQuestion('Confirm password', 'Str0ng!Passw0rd#2026')
        ->assertSuccessful();

    $superAdmin = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($superAdmin->hasRole(RoleName::SuperAdmin->value))->toBeTrue();
    expect($superAdmin->has_all_branch_access)->toBeTrue();
    expect($superAdmin->isActive())->toBeTrue();
    expect($superAdmin->email_verified_at)->not->toBeNull();
});

it('refuses a weak password and creates nothing', function (): void {
    $this->artisan('shop:create-super-admin', ['--name' => 'Weak', '--email' => 'weak@example.com'])
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();

    expect(User::query()->where('email', 'weak@example.com')->exists())->toBeFalse();
});

it('refuses a mismatching password confirmation', function (): void {
    $this->artisan('shop:create-super-admin', ['--name' => 'Mismatch', '--email' => 'mismatch@example.com'])
        ->expectsQuestion('Password', 'Str0ng!Passw0rd#2026')
        ->expectsQuestion('Confirm password', 'Different!Passw0rd#2026')
        ->assertFailed();

    expect(User::query()->where('email', 'mismatch@example.com')->exists())->toBeFalse();
});
