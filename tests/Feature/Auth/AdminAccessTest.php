<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
});

it('lets staff open the admin dashboard', function (RoleName $roleName): void {
    $this->actingAs(createStaffWithRole($roleName))
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('auth.dashboard_title', [], 'bn'));
})->with([
    'super administrator' => [RoleName::SuperAdmin],
    'branch manager' => [RoleName::BranchManager],
    'cashier' => [RoleName::Cashier],
]);

it('keeps customers out of the admin area', function (): void {
    $this->actingAs(createCustomer())->get('/admin')->assertForbidden();
});

it('keeps accounts without any role out of the admin area', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('keeps unverified staff out of the admin area', function (): void {
    $staffUser = createStaffWithRole(RoleName::BranchManager, ['email_verified_at' => null]);

    $this->actingAs($staffUser)
        ->get('/admin')
        ->assertRedirect(route('verification.notice', ['locale' => 'bn']));
});

it('shows the admin area in the language saved on the user profile', function (): void {
    $staffUser = createStaffWithRole(RoleName::BranchManager, ['preferred_locale' => 'en']);

    $this->actingAs($staffUser)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('auth.dashboard_title', [], 'en'));
});

it('signs out a user who was suspended after logging in', function (): void {
    $customer = createCustomer();
    $customer->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($customer)
        ->get('/bn/account')
        ->assertRedirect(route('login', ['locale' => 'bn']));

    $this->assertGuest();
});

it('keeps a branch-bound manager inside their own branch on the dashboard', function (): void {
    $ownBranch = \App\Models\Branch::factory()->create(['name' => 'Own Branch']);
    $otherBranch = \App\Models\Branch::factory()->create(['name' => 'Other Branch']);

    $manager = createBranchStaff($ownBranch);

    $this->actingAs($manager)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Own Branch')
        ->assertDontSee($otherBranch->name);
});
