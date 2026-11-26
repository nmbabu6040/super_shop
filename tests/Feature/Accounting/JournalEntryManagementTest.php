<?php

declare(strict_types=1);

use App\Enums\JournalEntryStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

function createHeadOfficeAccountant(): User
{
    return createStaffWithRole(RoleName::Accountant, ['has_all_branch_access' => true]);
}

/**
 * @param  array{cash: App\Models\Account, sales: App\Models\Account, rent: App\Models\Account, group: App\Models\Account}  $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function journalFormPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'entry_date' => '2026-03-15',
        'description' => 'Owner invested cash',
        'lines' => [
            ['account_id' => $fixture['cash']->id, 'debit' => '500.00', 'credit' => ''],
            ['account_id' => $fixture['sales']->id, 'debit' => '', 'credit' => '500.00'],
        ],
    ], $overrides);
}

/**
 * Applies a named invalid change to the valid form payload.
 *
 * @param  array{cash: App\Models\Account, sales: App\Models\Account, rent: App\Models\Account, group: App\Models\Account}  $fixture
 * @return array<string, mixed>
 */
function journalInvalidFormPayload(string $scenario, array $fixture): array
{
    $payload = journalFormPayload($fixture);

    switch ($scenario) {
        case 'unbalanced':
            $payload['lines'][1]['credit'] = '400.00';
            break;
        case 'a single line':
            $payload['lines'] = [$payload['lines'][0]];
            break;
        case 'debit and credit on one line':
            $payload['lines'][0]['credit'] = '5.00';
            break;
        case 'a line without an amount':
            $payload['lines'][0]['debit'] = '';
            $payload['lines'][1]['credit'] = '';
            break;
        case 'a group account':
            $payload['lines'][0]['account_id'] = $fixture['group']->id;
            break;
        case 'an unknown account':
            $payload['lines'][0]['account_id'] = 99999;
            break;
        case 'too many decimals':
            $payload['lines'][0]['debit'] = '500.123';
            break;
        case 'a missing description':
            $payload['description'] = '';
            break;
        case 'a bad date':
            $payload['entry_date'] = '15/03/2026';
            break;
    }

    return $payload;
}

it('submits a manual entry as pending without touching any balance', function (): void {
    $fixture = createAccountingFixture();
    $accountant = createHeadOfficeAccountant();

    $this->actingAs($accountant)
        ->post('/admin/journal-entries', journalFormPayload($fixture))
        ->assertRedirect();

    $entry = JournalEntry::withoutBranchScope()->with('lines')->firstOrFail();

    expect($entry->status)->toBe(JournalEntryStatus::Pending);
    expect($entry->created_by)->toEqual($accountant->id);
    expect($entry->entry_number)->toBe('JE-HO-2026-000001');
    expect($entry->total_amount)->toBe('500.00');
    expect($entry->lines)->toHaveCount(2);
    expect($entry->branch_id)->toBeNull();

    $this->actingAs($accountant)->get(route('admin.journal-entries.show', $entry))
        ->assertOk()
        ->assertSee('JE-HO-2026-000001');
});

it('rejects invalid manual entries', function (string $scenario, string $invalidField): void {
    $fixture = createAccountingFixture();

    $this->actingAs(createHeadOfficeAccountant())
        ->post('/admin/journal-entries', journalInvalidFormPayload($scenario, $fixture))
        ->assertSessionHasErrors($invalidField);

    expect(JournalEntry::withoutBranchScope()->count())->toBe(0);
})->with([
    ['unbalanced', 'lines'],
    ['a single line', 'lines'],
    ['debit and credit on one line', 'lines.0.debit'],
    ['a line without an amount', 'lines.0.debit'],
    ['a group account', 'lines.0.account_id'],
    ['an unknown account', 'lines.0.account_id'],
    ['too many decimals', 'lines.0.debit'],
    ['a missing description', 'description'],
    ['a bad date', 'entry_date'],
]);

it('shows a service error when the date has no open period', function (): void {
    $fixture = createAccountingFixture();

    $this->actingAs(createHeadOfficeAccountant())
        ->post('/admin/journal-entries', journalFormPayload($fixture, ['entry_date' => '2031-01-10']))
        ->assertSessionHas('error');

    expect(JournalEntry::withoutBranchScope()->count())->toBe(0);
});

it('lets branch-bound staff post only into their own branch', function (): void {
    $fixture = createAccountingFixture();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $bookkeeperRole = Role::create(['name' => 'branch-bookkeeper', 'guard_name' => 'web']);
    $bookkeeperRole->givePermissionTo(['journal_entries.view', 'journal_entries.create']);

    $bookkeeper = User::factory()->create();
    $bookkeeper->assignRole('branch-bookkeeper');
    $bookkeeper->branches()->attach($ownBranch);

    // Without a branch the working branch is used, for the entry and its lines.
    $this->actingAs($bookkeeper)->post('/admin/journal-entries', journalFormPayload($fixture))->assertRedirect();

    $entry = JournalEntry::withoutBranchScope()->with('lines')->firstOrFail();

    expect($entry->branch_id)->toEqual($ownBranch->id);
    expect($entry->lines->pluck('branch_id')->unique()->all())->toEqual([$ownBranch->id]);

    // Another branch is refused.
    $this->actingAs($bookkeeper)
        ->post('/admin/journal-entries', journalFormPayload($fixture, ['branch_id' => $otherBranch->id]))
        ->assertSessionHasErrors('branch_id');

    expect(JournalEntry::withoutBranchScope()->count())->toBe(1);
});

it('lets another person approve, and blocks self-approval', function (): void {
    $fixture = createAccountingFixture();
    $maker = createHeadOfficeAccountant();
    $checker = createHeadOfficeAccountant();

    $entry = app(JournalService::class)->createPending(balancedDraft($fixture), $maker);

    $this->actingAs($maker)->post(route('admin.journal-entries.approve', $entry))->assertSessionHas('error');
    expect(JournalEntry::withoutBranchScope()->findOrFail($entry->id)->status)->toBe(JournalEntryStatus::Pending);

    $this->actingAs($checker)->post(route('admin.journal-entries.approve', $entry))
        ->assertRedirect(route('admin.journal-entries.show', $entry))
        ->assertSessionHas('success');

    expect(JournalEntry::withoutBranchScope()->findOrFail($entry->id)->status)->toBe(JournalEntryStatus::Posted);
});

it('rejects an entry only with a reason', function (): void {
    $fixture = createAccountingFixture();
    $entry = app(JournalService::class)->createPending(balancedDraft($fixture), createHeadOfficeAccountant());
    $checker = createHeadOfficeAccountant();

    $this->actingAs($checker)->post(route('admin.journal-entries.reject', $entry), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($checker)->post(route('admin.journal-entries.reject', $entry), ['reason' => 'Wrong account'])
        ->assertRedirect(route('admin.journal-entries.show', $entry));

    $rejected = JournalEntry::withoutBranchScope()->findOrFail($entry->id);

    expect($rejected->status)->toBe(JournalEntryStatus::Rejected);
    expect($rejected->rejection_reason)->toBe('Wrong account');
});

it('reverses a posted entry with a reason and a date', function (): void {
    $fixture = createAccountingFixture();
    $original = app(JournalService::class)->post(balancedDraft($fixture));
    $accountant = createHeadOfficeAccountant();

    $this->actingAs($accountant)->post(route('admin.journal-entries.reverse', $original), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($accountant)
        ->post(route('admin.journal-entries.reverse', $original), ['reason' => 'Posted twice', 'reversal_date' => '2026-03-20'])
        ->assertSessionHas('success');

    $reversal = JournalEntry::withoutBranchScope()->where('reverses_entry_id', $original->id)->firstOrFail();

    expect($reversal->status)->toBe(JournalEntryStatus::Posted);
    expect($reversal->entry_date->format('Y-m-d'))->toBe('2026-03-20');
    expect(JournalEntry::withoutBranchScope()->findOrFail($original->id)->isReversed())->toBeTrue();

    // A second reversal is refused.
    $this->actingAs($accountant)
        ->post(route('admin.journal-entries.reverse', $original), ['reason' => 'Again', 'reversal_date' => '2026-03-21'])
        ->assertSessionHas('error');
});

it('offers no way to edit or delete entries', function (): void {
    $fixture = createAccountingFixture();
    $entry = app(JournalService::class)->post(balancedDraft($fixture));
    $accountant = createHeadOfficeAccountant();

    $this->actingAs($accountant)->delete('/admin/journal-entries/' . $entry->id)->assertStatus(405);
    $this->actingAs($accountant)->put('/admin/journal-entries/' . $entry->id, ['description' => 'x'])->assertStatus(405);

    expect(JournalEntry::withoutBranchScope()->count())->toBe(1);
});

it('lists, filters and searches entries', function (): void {
    $fixture = createAccountingFixture();
    $service = app(JournalService::class);
    $accountant = createHeadOfficeAccountant();

    $march = $service->post(balancedDraft($fixture, '10.00', '2026-03-15'));
    $may = $service->post(balancedDraft($fixture, '20.00', '2026-05-15'));
    $pending = $service->createPending(balancedDraft($fixture, '30.00', '2026-05-16'), $accountant);

    $this->actingAs($accountant)->get('/admin/journal-entries')->assertOk()
        ->assertViewHas('entries', fn ($entries): bool => $entries->pluck('id')->all() === [$pending->id, $may->id, $march->id]);

    $this->actingAs($accountant)->get('/admin/journal-entries?status=pending')
        ->assertViewHas('entries', fn ($entries): bool => $entries->pluck('id')->all() === [$pending->id]);

    $this->actingAs($accountant)->get('/admin/journal-entries?from=2026-05-01&to=2026-05-15')
        ->assertViewHas('entries', fn ($entries): bool => $entries->pluck('id')->all() === [$may->id]);

    $this->actingAs($accountant)->get('/admin/journal-entries?search=' . $march->entry_number)
        ->assertViewHas('entries', fn ($entries): bool => $entries->pluck('id')->all() === [$march->id]);
});

it('shows branch-bound staff only the entries of their own branch', function (): void {
    $fixture = createAccountingFixture();
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $service = app(JournalService::class);

    $ownEntry = $service->post(balancedDraft($fixture, branchId: $ownBranch->id));
    $otherEntry = $service->post(balancedDraft($fixture, branchId: $otherBranch->id));
    $headOfficeEntry = $service->post(balancedDraft($fixture));

    $manager = createBranchStaff($ownBranch);

    $this->actingAs($manager)->get('/admin/journal-entries')
        ->assertOk()
        ->assertViewHas('entries', fn ($entries): bool => $entries->pluck('id')->all() === [$ownEntry->id]);

    $this->actingAs($manager)->get(route('admin.journal-entries.show', $ownEntry))->assertOk();
    $this->actingAs($manager)->get(route('admin.journal-entries.show', $otherEntry))->assertNotFound();
    $this->actingAs($manager)->get(route('admin.journal-entries.show', $headOfficeEntry))->assertNotFound();
});

it('follows the permissions of each role', function (): void {
    $fixture = createAccountingFixture();
    $entry = app(JournalService::class)->post(balancedDraft($fixture));

    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/journal-entries')->assertForbidden();

    // A branch manager may look at entries but not create, approve or reverse them.
    $branch = Branch::factory()->create();
    $manager = createBranchStaff($branch);
    $branchEntry = app(JournalService::class)->post(balancedDraft($fixture, branchId: $branch->id));

    $this->actingAs($manager)->get('/admin/journal-entries')->assertOk();
    $this->actingAs($manager)->get('/admin/journal-entries/create')->assertForbidden();
    $this->actingAs($manager)->post(route('admin.journal-entries.reverse', $branchEntry), ['reason' => 'x'])->assertForbidden();

    $accountant = createHeadOfficeAccountant();

    $this->actingAs($accountant)->get('/admin/journal-entries/create')->assertOk();
    $this->actingAs($accountant)->get(route('admin.journal-entries.show', $entry))->assertOk();
});
