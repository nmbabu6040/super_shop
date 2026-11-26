<?php

declare(strict_types=1);

use App\Enums\PeriodStatus;
use App\Enums\RoleName;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

it('creates a fiscal year with twelve periods', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/fiscal-years', ['name' => 'FY 2028', 'starts_on' => '2028-01-01'])
        ->assertRedirect(route('admin.fiscal-years.index'));

    $fiscalYear = FiscalYear::query()->where('name', 'FY 2028')->firstOrFail();

    expect($fiscalYear->periods)->toHaveCount(12);
    expect($fiscalYear->periods->first()->status)->toBe(PeriodStatus::Open);
});

it('rejects invalid fiscal years', function (array $payload, bool $isFormError): void {
    createAccountingFixture();

    $response = $this->actingAs(createHeadOfficeAdmin())->post('/admin/fiscal-years', $payload);

    $isFormError ? $response->assertSessionHasErrors() : $response->assertSessionHas('error');

    expect(FiscalYear::query()->count())->toBe(1);
})->with([
    'missing name' => [['name' => '', 'starts_on' => '2028-01-01'], true],
    'wrong date format' => [['name' => 'FY 2028', 'starts_on' => '01/01/2028'], true],
    'not the first day of a month' => [['name' => 'FY 2028', 'starts_on' => '2028-01-15'], false],
    'overlapping year' => [['name' => 'FY other', 'starts_on' => '2026-07-01'], false],
    'name already used' => [['name' => 'FY 2026', 'starts_on' => '2027-01-01'], false],
]);

it('deletes an unused fiscal year but not one with postings', function (): void {
    $fixture = createAccountingFixture();
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/fiscal-years', ['name' => 'FY 2027', 'starts_on' => '2027-01-01']);
    $unusedYear = FiscalYear::query()->where('name', 'FY 2027')->firstOrFail();

    $this->actingAs($admin)->delete(route('admin.fiscal-years.destroy', $unusedYear))
        ->assertRedirect(route('admin.fiscal-years.index'));

    expect(FiscalYear::query()->where('name', 'FY 2027')->exists())->toBeFalse();

    app(JournalService::class)->post(balancedDraft($fixture));

    $this->actingAs($admin)
        ->delete(route('admin.fiscal-years.destroy', FiscalYear::query()->where('name', 'FY 2026')->firstOrFail()))
        ->assertSessionHas('error');

    expect(FiscalYear::query()->where('name', 'FY 2026')->exists())->toBeTrue();
});

it('closes and reopens a period', function (): void {
    createAccountingFixture();
    $admin = createHeadOfficeAdmin();
    $period = AccountingPeriod::query()->where('name', '2026-03')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.fiscal-years.periods.close', $period))
        ->assertRedirect(route('admin.fiscal-years.index'));

    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);
    expect($period->fresh()->closed_by)->toEqual($admin->id);

    $this->actingAs($admin)->post(route('admin.fiscal-years.periods.reopen', $period))
        ->assertRedirect(route('admin.fiscal-years.index'));

    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
});

it('does not close a period with entries waiting for approval', function (): void {
    $fixture = createAccountingFixture();
    app(JournalService::class)->createPending(balancedDraft($fixture), createStaffWithRole(RoleName::Accountant));
    $period = AccountingPeriod::query()->where('name', '2026-03')->firstOrFail();

    $this->actingAs(createHeadOfficeAdmin())
        ->post(route('admin.fiscal-years.periods.close', $period))
        ->assertSessionHas('error');

    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
});

it('lets a user who may only update accounts close but not reopen a period', function (): void {
    createAccountingFixture();

    $bookkeeperRole = Role::create(['name' => 'bookkeeper', 'guard_name' => 'web']);
    $bookkeeperRole->givePermissionTo(['accounts.view', 'accounts.update']);

    $bookkeeper = User::factory()->create();
    $bookkeeper->assignRole('bookkeeper');

    $period = AccountingPeriod::query()->where('name', '2026-05')->firstOrFail();

    $this->actingAs($bookkeeper)->get('/admin/fiscal-years')->assertOk();
    $this->actingAs($bookkeeper)->post(route('admin.fiscal-years.periods.close', $period))
        ->assertRedirect(route('admin.fiscal-years.index'));

    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);

    $this->actingAs($bookkeeper)->post(route('admin.fiscal-years.periods.reopen', $period))->assertForbidden();
    $this->actingAs($bookkeeper)->get('/admin/fiscal-years/create')->assertForbidden();

    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);
});

it('keeps fiscal years away from roles without account permissions', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/fiscal-years')->assertForbidden();
    $this->actingAs(createStaffWithRole(RoleName::Accountant))->get('/admin/fiscal-years')->assertOk();
});
