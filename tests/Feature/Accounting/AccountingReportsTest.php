<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\RoleName;
use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\JournalService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

function createReportAccountant(): User
{
    return createStaffWithRole(RoleName::Accountant, ['has_all_branch_access' => true]);
}

it('shows a trial balance with totals', function (): void {
    $fixture = createAccountingFixture();
    app(JournalService::class)->post(balancedDraft($fixture, '100.00'));

    $this->actingAs(createReportAccountant())
        ->get('/admin/reports/trial-balance?as_of=2026-12-31')
        ->assertOk()
        ->assertViewHas('report', fn (array $report): bool => $report['total_debit'] === '100.00' && $report['total_credit'] === '100.00');
});

it('opens with default dates', function (): void {
    createAccountingFixture();
    $accountant = createReportAccountant();

    $this->actingAs($accountant)->get('/admin/reports/trial-balance')->assertOk();
    $this->actingAs($accountant)->get('/admin/reports/general-ledger')->assertOk()->assertViewHas('ledger', null);
});

it('limits branch-bound staff to the figures of their own branches', function (): void {
    $fixture = createAccountingFixture();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00', branchId: $ownBranch->id));
    $service->post(balancedDraft($fixture, '50.00', branchId: $otherBranch->id));
    $service->post(balancedDraft($fixture, '10.00'));

    $manager = createBranchStaff($ownBranch);

    // No filter: only the own branch (not the other branch, not head office level lines).
    $this->actingAs($manager)->get('/admin/reports/trial-balance?as_of=2026-12-31')
        ->assertOk()
        ->assertViewHas('report', fn (array $report): bool => $report['total_debit'] === '100.00');

    // Asking for another branch is refused.
    $this->actingAs($manager)->get('/admin/reports/trial-balance?as_of=2026-12-31&branch_id=' . $otherBranch->id)
        ->assertSessionHasErrors('branch_id');

    // Head office sees everything, or one branch when it chooses.
    $accountant = createReportAccountant();

    $this->actingAs($accountant)->get('/admin/reports/trial-balance?as_of=2026-12-31')
        ->assertViewHas('report', fn (array $report): bool => $report['total_debit'] === '160.00');
    $this->actingAs($accountant)->get('/admin/reports/trial-balance?as_of=2026-12-31&branch_id=' . $otherBranch->id)
        ->assertViewHas('report', fn (array $report): bool => $report['total_debit'] === '50.00');
});

it('validates the report filters', function (string $query, string $invalidField): void {
    createAccountingFixture();

    $this->actingAs(createReportAccountant())
        ->get('/admin/reports/general-ledger?' . $query)
        ->assertSessionHasErrors($invalidField);
})->with([
    ['from=2026/01/01', 'from'],
    ['from=2026-05-01&to=2026-04-01', 'to'],
    ['account_id=99999', 'account_id'],
]);

it('builds a general ledger with opening balance, running balance and closing balance', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00', '2026-02-10'));
    $service->post(balancedDraft($fixture, '40.00', '2026-03-05'));
    $service->post(new JournalDraft(CarbonImmutable::parse('2026-03-20'), 'Rent', [
        JournalLineDraft::debit($fixture['rent']->id, '30.00'),
        JournalLineDraft::credit($fixture['cash']->id, '30.00'),
    ]));
    $service->post(balancedDraft($fixture, '5.00', '2026-04-10'));

    $ledger = app(AccountBalanceService::class)
        ->ledger($fixture['cash'], CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

    expect($ledger['opening'])->toBe('100.00');
    expect(array_column($ledger['rows'], 'balance'))->toBe(['140.00', '110.00']);
    expect($ledger['rows'][0]['entry_date'])->toBe('2026-03-05');
    expect($ledger['total_debit'])->toBe('40.00');
    expect($ledger['total_credit'])->toBe('30.00');
    expect($ledger['closing'])->toBe('110.00');
    expect($ledger['truncated'])->toBeFalse();
});

it('shows balances of credit accounts on their normal side', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '100.00', '2026-03-05'));

    $ledger = app(AccountBalanceService::class)
        ->ledger($fixture['sales'], CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

    // Sales is a revenue account: a credit makes the balance grow.
    expect($ledger['account']->type)->toBe(AccountType::Revenue);
    expect($ledger['rows'][0]['balance'])->toBe('100.00');
    expect($ledger['closing'])->toBe('100.00');
});

it('filters the ledger by branch and caps the listed lines', function (): void {
    $fixture = createAccountingFixture();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, '10.00', '2026-03-05', $branchA->id));
    $service->post(balancedDraft($fixture, '20.00', '2026-03-06', $branchB->id));
    $service->post(balancedDraft($fixture, '30.00', '2026-03-07', $branchB->id));

    $balances = app(AccountBalanceService::class);
    $from = CarbonImmutable::parse('2026-03-01');
    $to = CarbonImmutable::parse('2026-03-31');

    expect($balances->ledger($fixture['cash'], $from, $to, [$branchA->id])['closing'])->toBe('10.00');
    expect($balances->ledger($fixture['cash'], $from, $to, [$branchB->id])['closing'])->toBe('50.00');

    $capped = $balances->ledger($fixture['cash'], $from, $to, null, 2);

    expect($capped['rows'])->toHaveCount(2);
    expect($capped['truncated'])->toBeTrue();
    // Totals and the closing balance still cover every line.
    expect($capped['closing'])->toBe('60.00');
});

it('renders the ledger page for a chosen account', function (): void {
    $fixture = createAccountingFixture();
    $entry = app(JournalService::class)->post(balancedDraft($fixture, '100.00', '2026-03-05'));

    $this->actingAs(createReportAccountant())
        ->get('/admin/reports/general-ledger?account_id=' . $fixture['cash']->id . '&from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertSee($entry->entry_number)
        ->assertViewHas('ledger', fn (array $ledger): bool => $ledger['closing'] === '100.00');
});

it('exports the trial balance and the ledger as CSV and neutralises formulas', function (): void {
    $fixture = createAccountingFixture();
    app(JournalService::class)->post(new JournalDraft(CarbonImmutable::parse('2026-03-05'), '=HYPERLINK("http://evil")', [
        JournalLineDraft::debit($fixture['cash']->id, '100.00'),
        JournalLineDraft::credit($fixture['sales']->id, '100.00'),
    ]));
    $accountant = createReportAccountant();

    $trial = $this->actingAs($accountant)->get('/admin/reports/trial-balance/export?as_of=2026-12-31');

    $trial->assertOk();
    expect($trial->headers->get('content-type'))->toContain('text/csv');
    expect($trial->streamedContent())->toContain('1110')->toContain('TOTAL');

    $ledger = $this->actingAs($accountant)
        ->get('/admin/reports/general-ledger/export?account_id=' . $fixture['cash']->id . '&from=2026-03-01&to=2026-03-31')
        ->streamedContent();

    expect($ledger)->toContain("'=HYPERLINK");
    expect($ledger)->not->toContain(',=HYPERLINK');
});

it('follows the permissions of each role', function (): void {
    createAccountingFixture();

    // No financial reports for a cashier or a sales executive.
    foreach ([RoleName::Cashier, RoleName::SalesExecutive] as $roleName) {
        $user = createStaffWithRole($roleName);

        $this->actingAs($user)->get('/admin/reports/trial-balance')->assertForbidden();
        $this->actingAs($user)->get('/admin/reports/general-ledger')->assertForbidden();
    }

    // A branch manager may view (and export) the reports of their own branch.
    $branch = Branch::factory()->create();
    $manager = createBranchStaff($branch);

    $this->actingAs($manager)->get('/admin/reports/trial-balance')->assertOk();
    $this->actingAs($manager)->get('/admin/reports/trial-balance/export')->assertOk();
});

it('refuses the ledger export without an account', function (): void {
    createAccountingFixture();

    $this->actingAs(createReportAccountant())
        ->get('/admin/reports/general-ledger/export')
        ->assertNotFound();
});
