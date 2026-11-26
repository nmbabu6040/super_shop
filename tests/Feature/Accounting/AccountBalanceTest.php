<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\JournalService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

it('builds a balanced trial balance from posted entries', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00'));
    $service->post(new JournalDraft(CarbonImmutable::parse('2026-03-16'), 'Rent', [
        JournalLineDraft::debit($fixture['rent']->id, '30.00'),
        JournalLineDraft::credit($fixture['cash']->id, '30.00'),
    ]));

    $report = app(AccountBalanceService::class)->trialBalance(CarbonImmutable::parse('2026-12-31'));

    expect(array_map(fn (array $row): string => $row['account']->code, $report['rows']))->toBe(['1110', '4100', '6100']);

    // Cash: 100 in, 30 out = 70 debit. Sales: 100 credit. Rent: 30 debit.
    expect($report['rows'][0]['debit'])->toBe('70.00');
    expect($report['rows'][0]['credit'])->toBe('0.00');
    expect($report['rows'][1]['debit'])->toBe('0.00');
    expect($report['rows'][1]['credit'])->toBe('100.00');
    expect($report['rows'][2]['debit'])->toBe('30.00');

    expect($report['total_debit'])->toBe('100.00');
    expect($report['total_credit'])->toBe('100.00');
});

it('only counts entries up to the chosen date', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00', '2026-03-15'));
    $service->post(balancedDraft($fixture, '50.00', '2026-05-20'));

    $balances = app(AccountBalanceService::class);

    expect($balances->trialBalance(CarbonImmutable::parse('2026-04-30'))['total_debit'])->toBe('100.00');
    expect($balances->trialBalance(CarbonImmutable::parse('2026-05-20'))['total_debit'])->toBe('150.00');
    expect($balances->trialBalance(CarbonImmutable::parse('2026-03-14'))['rows'])->toBe([]);

    // A start date narrows the range from the other side.
    $cashTotals = $balances->totalsByAccount(CarbonImmutable::parse('2026-12-31'), null, CarbonImmutable::parse('2026-04-01'))
        ->firstWhere('account_id', $fixture['cash']->id);

    expect((string) $cashTotals->total_debit)->toBe('50.00');
});

it('filters balances by branch', function (): void {
    $fixture = createAccountingFixture();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00', branchId: $branchA->id));
    $service->post(balancedDraft($fixture, '50.00', branchId: $branchB->id));
    $service->post(balancedDraft($fixture, '10.00'));

    $balances = app(AccountBalanceService::class);
    $asOf = CarbonImmutable::parse('2026-12-31');

    expect($balances->trialBalance($asOf, [$branchA->id])['total_debit'])->toBe('100.00');
    expect($balances->trialBalance($asOf, [$branchA->id, $branchB->id])['total_debit'])->toBe('150.00');
    expect($balances->trialBalance($asOf)['total_debit'])->toBe('160.00');
    // An empty list means "no branch is allowed" and matches nothing.
    expect($balances->trialBalance($asOf, [])['rows'])->toBe([]);
});

it('ignores pending and rejected entries', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(RoleName::Accountant);
    $checker = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $service->createPending(balancedDraft($fixture, '100.00'), $maker);
    $rejected = $service->createPending(balancedDraft($fixture, '200.00'), $maker);
    $service->reject($rejected, $checker, 'No');

    $report = app(AccountBalanceService::class)->trialBalance(CarbonImmutable::parse('2026-12-31'));

    expect($report['rows'])->toBe([]);
    expect($report['total_debit'])->toBe('0.00');
});

it('nets a reversed entry out to zero', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $original = $service->post(balancedDraft($fixture, '100.00'));
    $service->reverse($original, createStaffWithRole(RoleName::Accountant), 'Mistake', CarbonImmutable::parse('2026-03-20'));

    $report = app(AccountBalanceService::class)->trialBalance(CarbonImmutable::parse('2026-12-31'));

    expect($report['total_debit'])->toBe('0.00');
    expect($report['total_credit'])->toBe('0.00');

    foreach ($report['rows'] as $row) {
        expect($row['debit'])->toBe('0.00');
        expect($row['credit'])->toBe('0.00');
    }
});

it('keeps exact decimal totals', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    foreach (range(1, 10) as $ignored) {
        $service->post(balancedDraft($fixture, '0.10'));
    }

    $report = app(AccountBalanceService::class)->trialBalance(CarbonImmutable::parse('2026-12-31'));

    expect($report['total_debit'])->toBe('1.00');
    expect($report['total_credit'])->toBe('1.00');
});
