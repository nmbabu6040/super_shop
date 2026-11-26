<?php

declare(strict_types=1);

use App\Enums\JournalEntryStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalCalendarService;
use App\Services\Accounting\JournalService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

it('creates a fiscal year with twelve consecutive monthly periods', function (): void {
    $fiscalYear = app(FiscalCalendarService::class)->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    expect($fiscalYear->ends_on->format('Y-m-d'))->toBe('2026-12-31');
    expect($fiscalYear->periods)->toHaveCount(12);
    expect($fiscalYear->periods->first()->name)->toBe('2026-01');
    expect($fiscalYear->periods->last()->name)->toBe('2026-12');
    expect($fiscalYear->periods[1]->starts_on->format('Y-m-d'))->toBe('2026-02-01');
    expect($fiscalYear->periods[1]->ends_on->format('Y-m-d'))->toBe('2026-02-28');
});

it('supports a fiscal year that does not start in January', function (): void {
    $fiscalYear = app(FiscalCalendarService::class)->createFiscalYear('FY 2026-27', CarbonImmutable::parse('2026-07-01'));

    expect($fiscalYear->ends_on->format('Y-m-d'))->toBe('2027-06-30');
    expect($fiscalYear->periods->pluck('name')->first())->toBe('2026-07');
    expect($fiscalYear->periods->pluck('name')->last())->toBe('2027-06');
});

it('refuses invalid fiscal years', function (string $name, string $startsOn): void {
    $service = app(FiscalCalendarService::class);
    $service->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    expect(fn () => $service->createFiscalYear($name, CarbonImmutable::parse($startsOn)))
        ->toThrow(BusinessRuleException::class);

    expect(FiscalYear::query()->count())->toBe(1);
})->with([
    'does not start on the first day' => ['FY odd', '2027-01-15'],
    'overlaps an existing year' => ['FY overlap', '2026-07-01'],
    'name already used' => ['FY 2026', '2027-01-01'],
]);

it('finds the open period of a date', function (): void {
    app(FiscalCalendarService::class)->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    $period = app(FiscalCalendarService::class)->openPeriodFor(CarbonImmutable::parse('2026-03-31'));

    expect($period->name)->toBe('2026-03');
});

it('refuses dates without a period or in a closed period', function (): void {
    $service = app(FiscalCalendarService::class);
    $service->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));

    expect(fn () => $service->openPeriodFor(CarbonImmutable::parse('2027-02-01')))
        ->toThrow(BusinessRuleException::class);

    $service->closePeriod(AccountingPeriod::query()->where('name', '2026-03')->firstOrFail(), createHeadOfficeAdmin());

    expect(fn () => $service->openPeriodFor(CarbonImmutable::parse('2026-03-10')))
        ->toThrow(BusinessRuleException::class);

    expect($service->openPeriodFor(CarbonImmutable::parse('2026-04-10'))->name)->toBe('2026-04');
});

it('closes and reopens a period and records who closed it', function (): void {
    $service = app(FiscalCalendarService::class);
    $service->createFiscalYear('FY 2026', CarbonImmutable::parse('2026-01-01'));
    $actor = createHeadOfficeAdmin();
    $period = AccountingPeriod::query()->where('name', '2026-02')->firstOrFail();

    $service->closePeriod($period, $actor);

    $closedPeriod = $period->fresh();
    expect($closedPeriod->status)->toBe(PeriodStatus::Closed);
    expect($closedPeriod->closed_by)->toEqual($actor->id);
    expect($closedPeriod->closed_at)->not->toBeNull();

    $service->reopenPeriod($period);

    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
    expect($period->fresh()->closed_by)->toBeNull();
});

it('does not close a period that still has entries waiting for approval', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(App\Enums\RoleName::Accountant);

    $entry = app(JournalService::class)->createPending(balancedDraft($fixture), $maker);

    expect($entry->status)->toBe(JournalEntryStatus::Pending);

    expect(fn () => app(FiscalCalendarService::class)->closePeriod(
        AccountingPeriod::query()->where('name', '2026-03')->firstOrFail(),
        createHeadOfficeAdmin()
    ))->toThrow(BusinessRuleException::class);
});

it('deletes only a fiscal year that has no postings', function (): void {
    $fixture = createAccountingFixture();
    $service = app(FiscalCalendarService::class);
    $emptyYear = $service->createFiscalYear('FY 2027', CarbonImmutable::parse('2027-01-01'));

    $service->deleteFiscalYear($emptyYear);

    expect(FiscalYear::query()->where('name', 'FY 2027')->exists())->toBeFalse();
    expect(AccountingPeriod::query()->where('name', 'like', '2027-%')->count())->toBe(0);

    app(JournalService::class)->post(balancedDraft($fixture));

    expect(fn () => $service->deleteFiscalYear(FiscalYear::query()->where('name', 'FY 2026')->firstOrFail()))
        ->toThrow(BusinessRuleException::class);
});
