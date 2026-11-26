<?php

declare(strict_types=1);

use App\Enums\JournalEntryStatus;
use App\Enums\RoleName;
use App\Exceptions\BranchAccessDeniedException;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\FiscalCalendarService;
use App\Services\Accounting\JournalService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

/**
 * Builds the invalid draft of a named scenario (datasets hold names, not closures).
 *
 * @param  array{cash: App\Models\Account, sales: App\Models\Account, rent: App\Models\Account, group: App\Models\Account}  $fixture
 */
function journalInvalidDraftFor(string $scenario, array $fixture): JournalDraft
{
    $date = CarbonImmutable::parse('2026-03-15');

    return match ($scenario) {
        'unbalanced' => new JournalDraft($date, 'x', [
            JournalLineDraft::debit($fixture['cash']->id, '100'),
            JournalLineDraft::credit($fixture['sales']->id, '90'),
        ]),
        'a single line' => new JournalDraft($date, 'x', [
            JournalLineDraft::debit($fixture['cash']->id, '100'),
        ]),
        'a line with debit and credit' => new JournalDraft($date, 'x', [
            new JournalLineDraft($fixture['cash']->id, '10', '10'),
            JournalLineDraft::credit($fixture['sales']->id, '0.01'),
        ]),
        'a line without an amount' => new JournalDraft($date, 'x', [
            new JournalLineDraft($fixture['cash']->id, '0', '0'),
            JournalLineDraft::credit($fixture['sales']->id, '10'),
        ]),
        'more than two decimals' => balancedDraft($fixture, '10.123'),
        'a negative amount' => balancedDraft($fixture, '-5'),
        'text instead of an amount' => balancedDraft($fixture, 'ten'),
        'a group account' => new JournalDraft($date, 'x', [
            JournalLineDraft::debit($fixture['group']->id, '10'),
            JournalLineDraft::credit($fixture['sales']->id, '10'),
        ]),
        'an inactive account' => (function () use ($fixture, $date): JournalDraft {
            $fixture['rent']->update(['is_active' => false]);

            return new JournalDraft($date, 'x', [
                JournalLineDraft::debit($fixture['rent']->id, '10'),
                JournalLineDraft::credit($fixture['cash']->id, '10'),
            ]);
        })(),
        'an unknown account' => new JournalDraft($date, 'x', [
            JournalLineDraft::debit(999999, '10'),
            JournalLineDraft::credit($fixture['sales']->id, '10'),
        ]),
        'an unknown branch' => balancedDraft($fixture, branchId: 999999),
        'a date without a period' => balancedDraft($fixture, date: '2030-01-01'),
        'a closed period' => (function () use ($fixture): JournalDraft {
            app(FiscalCalendarService::class)->closePeriod(
                AccountingPeriod::query()->where('name', '2026-03')->firstOrFail(),
                createHeadOfficeAdmin()
            );

            return balancedDraft($fixture);
        })(),
    };
}

it('posts a balanced entry with number, period, lines and total', function (): void {
    $fixture = createAccountingFixture();

    $entry = app(JournalService::class)->post(balancedDraft($fixture));

    expect($entry->status)->toBe(JournalEntryStatus::Posted);
    expect($entry->entry_number)->toBe('JE-HO-2026-000001');
    expect($entry->total_amount)->toBe('100.00');
    expect($entry->accountingPeriod->name)->toBe('2026-03');
    expect($entry->lines)->toHaveCount(2);
    expect($entry->lines[0]->account_id)->toEqual($fixture['cash']->id);
    expect($entry->lines[0]->debit)->toBe('100.00');
    expect($entry->lines[0]->credit)->toBe('0.00');
    expect($entry->lines[1]->credit)->toBe('100.00');
});

it('numbers entries per branch and year', function (): void {
    $fixture = createAccountingFixture();
    $branch = Branch::factory()->create(['code' => 'BR-001']);
    $service = app(JournalService::class);

    $first = $service->post(balancedDraft($fixture, branchId: $branch->id));
    $second = $service->post(balancedDraft($fixture, branchId: $branch->id));
    $headOffice = $service->post(balancedDraft($fixture));

    expect($first->entry_number)->toBe('JE-BR-001-2026-000001');
    expect($second->entry_number)->toBe('JE-BR-001-2026-000002');
    expect($headOffice->entry_number)->toBe('JE-HO-2026-000001');
});

it('carries the branch to the lines unless a line names its own', function (): void {
    $fixture = createAccountingFixture();
    $entryBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $entry = app(JournalService::class)->post(new JournalDraft(
        CarbonImmutable::parse('2026-03-15'),
        'Split sale',
        [
            JournalLineDraft::debit($fixture['cash']->id, '100'),
            JournalLineDraft::credit($fixture['sales']->id, '60'),
            JournalLineDraft::credit($fixture['rent']->id, '40', null, $otherBranch->id),
        ],
        $entryBranch->id,
    ));

    expect($entry->lines->pluck('branch_id')->all())->toEqual([$entryBranch->id, $entryBranch->id, $otherBranch->id]);
});

it('rejects invalid entries and stores nothing', function (string $scenario): void {
    $fixture = createAccountingFixture();

    expect(fn () => app(JournalService::class)->post(journalInvalidDraftFor($scenario, $fixture)))
        ->toThrow(BusinessRuleException::class);

    expect(JournalEntry::withoutBranchScope()->count())->toBe(0);
    expect(JournalEntryLine::query()->count())->toBe(0);
})->with([
    'unbalanced',
    'a single line',
    'a line with debit and credit',
    'a line without an amount',
    'more than two decimals',
    'a negative amount',
    'text instead of an amount',
    'a group account',
    'an inactive account',
    'an unknown account',
    'an unknown branch',
    'a date without a period',
    'a closed period',
]);

it('does not use up a number when an entry is refused', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    expect(fn () => $service->post(journalInvalidDraftFor('unbalanced', $fixture)))
        ->toThrow(BusinessRuleException::class);

    expect($service->post(balancedDraft($fixture))->entry_number)->toBe('JE-HO-2026-000001');
});

it('posts the same business event only once', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $first = $service->post(balancedDraft($fixture, sourceType: 'sale', sourceId: 42, sourceEvent: 'completed'));
    $second = $service->post(balancedDraft($fixture, sourceType: 'sale', sourceId: 42, sourceEvent: 'completed'));

    expect($second->id)->toEqual($first->id);
    expect(JournalEntry::withoutBranchScope()->count())->toBe(1);
    expect(JournalEntryLine::query()->count())->toBe(2);
});

it('allows different events of the same source', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $service->post(balancedDraft($fixture, sourceType: 'sale', sourceId: 42, sourceEvent: 'completed'));
    $service->post(balancedDraft($fixture, sourceType: 'sale', sourceId: 42, sourceEvent: 'refunded'));
    $service->post(balancedDraft($fixture, sourceType: 'sale', sourceId: 43, sourceEvent: 'completed'));

    expect(JournalEntry::withoutBranchScope()->count())->toBe(3);
});

it('keeps a manual entry pending until another person approves it', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(RoleName::Accountant);
    $checker = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $entry = $service->createPending(balancedDraft($fixture), $maker);

    expect($entry->status)->toBe(JournalEntryStatus::Pending);
    expect($entry->created_by)->toEqual($maker->id);

    $approved = $service->approve($entry, $checker);

    expect($approved->status)->toBe(JournalEntryStatus::Posted);
    expect($approved->reviewed_by)->toEqual($checker->id);
    expect($approved->reviewed_at)->not->toBeNull();
});

it('does not let a person approve their own entry', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $entry = $service->createPending(balancedDraft($fixture), $maker);

    expect(fn () => $service->approve($entry, $maker))->toThrow(BusinessRuleException::class);

    expect(JournalEntry::withoutBranchScope()->findOrFail($entry->id)->status)->toBe(JournalEntryStatus::Pending);
});

it('lets a super administrator approve their own entry', function (): void {
    $fixture = createAccountingFixture();
    $owner = createStaffWithRole(RoleName::SuperAdmin);
    $service = app(JournalService::class);

    $entry = $service->createPending(balancedDraft($fixture), $owner);

    expect($service->approve($entry, $owner)->status)->toBe(JournalEntryStatus::Posted);
});

it('rejects a pending entry with a reason and never posts it afterwards', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(RoleName::Accountant);
    $checker = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $entry = $service->createPending(balancedDraft($fixture), $maker);
    $rejected = $service->reject($entry, $checker, 'Wrong account');

    expect($rejected->status)->toBe(JournalEntryStatus::Rejected);
    expect($rejected->rejection_reason)->toBe('Wrong account');
    expect($rejected->reviewed_by)->toEqual($checker->id);

    expect(fn () => $service->approve($entry, $checker))->toThrow(BusinessRuleException::class);
    expect(fn () => $service->reject($entry, $checker, 'Again'))->toThrow(BusinessRuleException::class);
});

it('checks the period and the accounts again when a pending entry is approved', function (): void {
    $fixture = createAccountingFixture();
    $maker = createStaffWithRole(RoleName::Accountant);
    $checker = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $entryForClosedPeriod = $service->createPending(balancedDraft($fixture), $maker);
    $entryForInactiveAccount = $service->createPending(balancedDraft($fixture, date: '2026-04-10'), $maker);

    // The period is closed while the first entry waits.
    AccountingPeriod::query()->where('name', '2026-03')->update(['status' => 'closed']);

    expect(fn () => $service->approve($entryForClosedPeriod, $checker))->toThrow(BusinessRuleException::class);

    // An account is deactivated while the second entry waits.
    $fixture['cash']->update(['is_active' => false]);

    expect(fn () => $service->approve($entryForInactiveAccount, $checker))->toThrow(BusinessRuleException::class);

    expect(JournalEntry::withoutBranchScope()->where('status', 'posted')->count())->toBe(0);
});

it('never changes or deletes a posted entry or its lines', function (): void {
    $fixture = createAccountingFixture();
    $entry = app(JournalService::class)->post(balancedDraft($fixture));
    $line = $entry->lines->first();

    expect(fn () => $entry->update(['description' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect(fn () => $line->update(['debit' => '1.00']))->toThrow(LogicException::class);
    expect(fn () => $line->delete())->toThrow(LogicException::class);

    expect(JournalEntry::withoutBranchScope()->findOrFail($entry->id)->description)->toBe('Test sale');
    expect(JournalEntryLine::query()->count())->toBe(2);
});

it('reverses a posted entry with a swapped, linked and posted entry', function (): void {
    $fixture = createAccountingFixture();
    $actor = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $original = $service->post(balancedDraft($fixture));
    $reversal = $service->reverse($original, $actor, 'Customer returned goods', CarbonImmutable::parse('2026-03-20'));

    expect($reversal->status)->toBe(JournalEntryStatus::Posted);
    expect($reversal->reverses_entry_id)->toEqual($original->id);
    expect($reversal->entry_date->format('Y-m-d'))->toBe('2026-03-20');
    expect($reversal->description)->toContain($original->entry_number);
    expect($reversal->lines[0]->account_id)->toEqual($fixture['cash']->id);
    expect($reversal->lines[0]->debit)->toBe('0.00');
    expect($reversal->lines[0]->credit)->toBe('100.00');
    expect($reversal->lines[1]->debit)->toBe('100.00');

    expect(JournalEntry::withoutBranchScope()->findOrFail($original->id)->isReversed())->toBeTrue();
});

it('reverses on the current date when none is given', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);

    $original = $service->post(balancedDraft($fixture));

    $this->travelTo(CarbonImmutable::parse('2026-06-10 10:00:00'));

    $reversal = $service->reverse($original, createStaffWithRole(RoleName::Accountant), 'Mistake');

    expect($reversal->entry_date->format('Y-m-d'))->toBe('2026-06-10');
});

it('refuses wrong reversals', function (): void {
    $fixture = createAccountingFixture();
    $actor = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    // Reversed twice.
    $original = $service->post(balancedDraft($fixture));
    $reversal = $service->reverse($original, $actor, 'First', CarbonImmutable::parse('2026-03-20'));

    expect(fn () => $service->reverse($original, $actor, 'Second', CarbonImmutable::parse('2026-03-21')))
        ->toThrow(BusinessRuleException::class);

    // A reversing entry cannot be reversed.
    expect(fn () => $service->reverse($reversal, $actor, 'Undo', CarbonImmutable::parse('2026-03-22')))
        ->toThrow(BusinessRuleException::class);

    // A pending entry cannot be reversed.
    $pending = $service->createPending(balancedDraft($fixture), $actor);

    expect(fn () => $service->reverse($pending, $actor, 'Nope', CarbonImmutable::parse('2026-03-22')))
        ->toThrow(BusinessRuleException::class);

    expect(JournalEntry::withoutBranchScope()->whereNotNull('reverses_entry_id')->count())->toBe(1);
});

it('does not reverse into a closed period and leaves the original unmarked', function (): void {
    $fixture = createAccountingFixture();
    $actor = createStaffWithRole(RoleName::Accountant);
    $service = app(JournalService::class);

    $original = $service->post(balancedDraft($fixture));

    app(FiscalCalendarService::class)->closePeriod(
        AccountingPeriod::query()->where('name', '2026-04')->firstOrFail(),
        createHeadOfficeAdmin()
    );

    expect(fn () => $service->reverse($original, $actor, 'Late', CarbonImmutable::parse('2026-04-10')))
        ->toThrow(BusinessRuleException::class);

    expect(JournalEntry::withoutBranchScope()->findOrFail($original->id)->isReversed())->toBeFalse();
    expect(JournalEntry::withoutBranchScope()->count())->toBe(1);
});

it('lets branch-bound staff post only for their own branch', function (): void {
    $fixture = createAccountingFixture();
    $ownBranch = Branch::factory()->create(['code' => 'OWN']);
    $otherBranch = Branch::factory()->create(['code' => 'OTHER']);
    $service = app(JournalService::class);

    $this->actingAs(createBranchStaff($ownBranch));

    expect($service->post(balancedDraft($fixture, branchId: $ownBranch->id))->branch_id)->toEqual($ownBranch->id);

    // Another branch, and head office level entries, are refused.
    expect(fn () => $service->post(balancedDraft($fixture, branchId: $otherBranch->id)))
        ->toThrow(BranchAccessDeniedException::class);
    expect(fn () => $service->post(balancedDraft($fixture)))
        ->toThrow(BranchAccessDeniedException::class);

    // The refused attempts gave their numbers back.
    $this->actingAs(createHeadOfficeAdmin());

    expect($service->post(balancedDraft($fixture, branchId: $otherBranch->id))->entry_number)
        ->toBe('JE-OTHER-2026-000001');
    expect($service->post(balancedDraft($fixture))->entry_number)->toBe('JE-HO-2026-000001');
});

it('shows branch-bound staff only the entries of their own branches', function (): void {
    $fixture = createAccountingFixture();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $service = app(JournalService::class);

    $ownEntry = $service->post(balancedDraft($fixture, branchId: $ownBranch->id));
    $service->post(balancedDraft($fixture, branchId: $otherBranch->id));
    $service->post(balancedDraft($fixture));

    $this->actingAs(createBranchStaff($ownBranch));

    expect(JournalEntry::query()->pluck('id')->all())->toEqual([$ownEntry->id]);
});
