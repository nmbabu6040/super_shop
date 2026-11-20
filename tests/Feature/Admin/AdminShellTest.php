<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;

beforeEach(function (): void {
    seedRbac();
});

it('shows sidebar links only for areas the user may open', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee(route('admin.branches.index'))
        ->assertDontSee(route('admin.warehouses.index'));

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee(route('admin.branches.index'))
        ->assertSee(route('admin.warehouses.index'));
});

it('switches the admin language and remembers it on the profile', function (): void {
    $manager = createStaffWithRole(RoleName::BranchManager);

    $this->actingAs($manager)
        ->from('/admin')
        ->post('/admin/locale', ['locale' => 'en'])
        ->assertRedirect('/admin')
        ->assertSessionHas('locale', 'en');

    expect($manager->fresh()->preferred_locale)->toBe('en');
});

it('rejects an unsupported admin language', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::BranchManager))
        ->post('/admin/locale', ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

it('lets a head office user focus on one branch and clear the focus again', function (): void {
    $focusBranch = Branch::factory()->create(['name' => 'Focus Branch']);
    Branch::factory()->create(['name' => 'Other Branch']);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/active-branch', ['branch_id' => $focusBranch->id])
        ->assertSessionHas('active_branch_id', $focusBranch->id);

    $this->post('/admin/active-branch', ['branch_id' => null])
        ->assertSessionMissing('active_branch_id');
});

it('refuses to focus on a branch the user cannot access', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch))
        ->post('/admin/active-branch', ['branch_id' => $otherBranch->id])
        ->assertForbidden();
});

it('lists only accessible branches in the branch switcher', function (): void {
    $ownBranch = Branch::factory()->create(['name' => 'Alpha Own']);
    $secondOwnBranch = Branch::factory()->create(['name' => 'Beta Own']);
    Branch::factory()->create(['name' => 'Gamma Foreign']);

    $this->actingAs(createBranchStaff($ownBranch, $secondOwnBranch))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Alpha Own')
        ->assertSee('Beta Own')
        ->assertDontSee('Gamma Foreign');
});
