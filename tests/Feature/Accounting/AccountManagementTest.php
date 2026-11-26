<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\RoleName;
use App\Models\Account;
use App\Services\Accounting\JournalService;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountPayload(array $overrides = []): array
{
    return array_merge([
        'code' => '2222',
        'name' => ['en' => 'Test account', 'bn' => 'পরীক্ষা হিসাব'],
        'type' => 'asset',
        'is_postable' => '1',
        'is_active' => '1',
    ], $overrides);
}

it('creates a top-level account with the chosen type', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/accounts', accountPayload())
        ->assertRedirect(route('admin.accounts.index'));

    $account = Account::query()->where('code', '2222')->firstOrFail();

    expect($account->type)->toBe(AccountType::Asset);
    expect($account->is_postable)->toBeTrue();
    expect($account->name->toArray())->toBe(['en' => 'Test account', 'bn' => 'পরীক্ষা হিসাব']);
    expect($account->isSystem())->toBeFalse();
});

it('takes the type of the parent for a sub-account', function (): void {
    $group = Account::factory()->create(['code' => '3000', 'type' => AccountType::Equity, 'is_postable' => false]);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/accounts', accountPayload(['code' => '3100', 'type' => 'asset', 'parent_id' => $group->id]))
        ->assertRedirect(route('admin.accounts.index'));

    $child = Account::query()->where('code', '3100')->firstOrFail();

    expect($child->type)->toBe(AccountType::Equity);
    expect($child->parent_id)->toEqual($group->id);
});

it('rejects invalid account data', function (array $overrides, string $invalidField): void {
    Account::factory()->create(['code' => '1110']);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/accounts', accountPayload($overrides))
        ->assertSessionHasErrors($invalidField);

    expect(Account::query()->where('code', '2222')->exists())->toBeFalse();
})->with([
    'code too short' => [['code' => '12'], 'code'],
    'code with letters' => [['code' => 'AB1234'], 'code'],
    'duplicate code' => [['code' => '1110'], 'code'],
    'missing English name' => [['name' => ['en' => '']], 'name.en'],
    'unknown type' => [['type' => 'bogus'], 'type'],
    'missing type without a parent' => [['type' => ''], 'type'],
    'unknown parent' => [['parent_id' => 99999], 'parent_id'],
]);

it('refuses a parent that is a posting account', function (): void {
    $postable = Account::factory()->create(['code' => '1500', 'is_postable' => true]);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/accounts', accountPayload(['parent_id' => $postable->id]))
        ->assertSessionHas('error');

    expect(Account::query()->where('code', '2222')->exists())->toBeFalse();
});

it('updates the name and status but never the code', function (): void {
    $account = Account::factory()->create(['code' => '1500']);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.accounts.update', $account), accountPayload([
            'code' => '9999',
            'name' => ['en' => 'Renamed', 'bn' => 'নতুন নাম'],
            'type' => $account->type->value,
            'is_active' => '0',
        ]))
        ->assertRedirect(route('admin.accounts.index'));

    $account = $account->fresh();

    expect($account->code)->toBe('1500');
    expect($account->name->toArray()['en'])->toBe('Renamed');
    expect($account->is_active)->toBeFalse();
});

it('locks the type and structure of an account that has postings', function (): void {
    $fixture = createAccountingFixture();
    app(JournalService::class)->post(balancedDraft($fixture));
    $admin = createHeadOfficeAdmin();

    // Type change.
    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $fixture['cash']), accountPayload(['type' => 'liability']))
        ->assertSessionHas('error');

    // Turning it into a group.
    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $fixture['cash']), accountPayload(['type' => 'asset', 'is_postable' => '0']))
        ->assertSessionHas('error');

    $cash = $fixture['cash']->fresh();

    expect($cash->type)->toBe(AccountType::Asset);
    expect($cash->is_postable)->toBeTrue();
});

it('refuses to turn an account with sub-accounts into a posting account', function (): void {
    $group = Account::factory()->create(['code' => '3000', 'type' => AccountType::Equity, 'is_postable' => false]);
    Account::factory()->create(['code' => '3100', 'type' => AccountType::Equity, 'parent_id' => $group->id]);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.accounts.update', $group), accountPayload(['type' => 'equity', 'is_postable' => '1']))
        ->assertSessionHas('error');

    expect($group->fresh()->is_postable)->toBeFalse();
});

it('refuses to move an account below itself', function (): void {
    $parent = Account::factory()->create(['code' => '3000', 'is_postable' => false]);
    $child = Account::factory()->create(['code' => '3100', 'parent_id' => $parent->id, 'is_postable' => false]);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.accounts.update', $parent), accountPayload([
            'type' => 'asset',
            'is_postable' => '0',
            'parent_id' => $child->id,
        ]))
        ->assertSessionHas('error');

    expect($parent->fresh()->parent_id)->toBeNull();
});

it('protects system accounts', function (): void {
    $system = Account::factory()->create(['code' => '1110']);
    $system->forceFill(['system_key' => 'cash'])->save();
    $admin = createHeadOfficeAdmin();

    // Cannot be deactivated or deleted ...
    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $system), accountPayload(['type' => 'asset', 'is_active' => '0']))
        ->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.accounts.destroy', $system))->assertSessionHas('error');

    expect($system->fresh()->is_active)->toBeTrue();
    expect(Account::query()->whereKey($system->id)->exists())->toBeTrue();

    // ... but it can be renamed.
    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $system), accountPayload(['type' => 'asset', 'name' => ['en' => 'Cash on hand']]))
        ->assertRedirect(route('admin.accounts.index'));

    expect($system->fresh()->name->toArray()['en'])->toBe('Cash on hand');
});

it('deletes only an unused account without sub-accounts', function (): void {
    $fixture = createAccountingFixture();
    app(JournalService::class)->post(balancedDraft($fixture));
    $group = Account::factory()->create(['code' => '3000', 'is_postable' => false]);
    Account::factory()->create(['code' => '3100', 'parent_id' => $group->id]);
    $unused = Account::factory()->create(['code' => '9000']);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.accounts.destroy', $fixture['cash']))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.accounts.destroy', $group))->assertSessionHas('error');

    expect(Account::query()->whereKey($fixture['cash']->id)->exists())->toBeTrue();
    expect(Account::query()->whereKey($group->id)->exists())->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.accounts.destroy', $unused))
        ->assertRedirect(route('admin.accounts.index'));

    expect(Account::query()->whereKey($unused->id)->exists())->toBeFalse();
});

it('lists accounts as a tree and flat when filtered', function (): void {
    $group = Account::factory()->create(['code' => '3000', 'type' => AccountType::Equity, 'is_postable' => false]);
    $child = Account::factory()->create(['code' => '3100', 'type' => AccountType::Equity, 'parent_id' => $group->id]);
    $asset = Account::factory()->create(['code' => '1110', 'type' => AccountType::Asset]);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->get('/admin/accounts')
        ->assertOk()
        ->assertSee('1110')
        ->assertViewHas('rows', fn (array $rows): bool => array_map(
            fn (array $row): string => $row['account']->code . ':' . $row['depth'],
            $rows
        ) === ['1110:0', '3000:0', '3100:1']);

    $this->actingAs($admin)
        ->get('/admin/accounts?type=equity')
        ->assertViewHas('rows', fn (array $rows): bool => collect($rows)->pluck('account.id')->sort()->values()->all()
            === collect([$group->id, $child->id])->sort()->values()->all());

    $this->actingAs($admin)
        ->get('/admin/accounts?search=1110')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 1 && $rows[0]['account']->id === $asset->id);
});

it('follows the permissions of each role', function (): void {
    $account = Account::factory()->create();

    foreach ([RoleName::Cashier, RoleName::HrManager, RoleName::BranchManager] as $roleName) {
        $this->actingAs(createStaffWithRole($roleName))->get('/admin/accounts')->assertForbidden();
    }

    $accountant = createStaffWithRole(RoleName::Accountant);

    $this->actingAs($accountant)->get('/admin/accounts')->assertOk();
    $this->actingAs($accountant)->get('/admin/accounts/create')->assertOk();
    $this->actingAs($accountant)->get(route('admin.accounts.edit', $account))->assertOk();
});
