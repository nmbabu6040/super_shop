<?php

declare(strict_types=1);

use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountRegistry;
use App\Services\Accounting\JournalService;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingSeeder;

it('creates every system account as an active posting account with Bangla names', function (): void {
    $this->seed(AccountingSeeder::class);

    foreach (SystemAccount::cases() as $systemAccount) {
        $account = Account::query()->where('system_key', $systemAccount->value)->first();

        expect($account)->not->toBeNull("Missing system account [{$systemAccount->value}]");
        expect($account->is_postable)->toBeTrue();
        expect($account->is_active)->toBeTrue();
        expect($account->name->toArray()['bn'] ?? '')->not->toBe('');
    }
});

it('builds a consistent tree: children share the type of their group parent', function (): void {
    $this->seed(AccountingSeeder::class);

    $cash = Account::query()->where('code', '1110')->with('parent.parent')->firstOrFail();

    expect($cash->parent->code)->toBe('1100');
    expect($cash->parent->is_postable)->toBeFalse();
    expect($cash->parent->parent->code)->toBe('1000');

    foreach (Account::query()->whereNotNull('parent_id')->with('parent')->get() as $account) {
        expect($account->parent->is_postable)->toBeFalse();
        expect($account->type)->toBe($account->parent->type);
    }
});

it('creates one fiscal year with twelve open periods', function (): void {
    $this->seed(AccountingSeeder::class);

    $fiscalYear = FiscalYear::query()->with('periods')->firstOrFail();

    expect(FiscalYear::query()->count())->toBe(1);
    expect($fiscalYear->name)->toBe('FY ' . now()->year);
    expect($fiscalYear->periods)->toHaveCount(12);
});

it('can be run twice without duplicating or overwriting anything', function (): void {
    $this->seed(AccountingSeeder::class);

    $accountCount = Account::query()->count();
    Account::query()->where('code', '1110')->firstOrFail()->update(['name' => ['en' => 'Till cash', 'bn' => 'ক্যাশ']]);

    $this->seed(AccountingSeeder::class);

    expect(Account::query()->count())->toBe($accountCount);
    expect(FiscalYear::query()->count())->toBe(1);
    expect(Account::query()->where('code', '1110')->firstOrFail()->name->toArray()['en'])->toBe('Till cash');
});

it('lets the application find its accounts by system key and post to them', function (): void {
    $this->seed(AccountingSeeder::class);

    $registry = app(AccountRegistry::class);
    $entry = app(JournalService::class)->post(new JournalDraft(
        CarbonImmutable::parse(now()->format('Y-m-15')),
        'Cash sale',
        [
            JournalLineDraft::debit($registry->id(SystemAccount::Cash), '115.00'),
            JournalLineDraft::credit($registry->id(SystemAccount::SalesRevenue), '100.00'),
            JournalLineDraft::credit($registry->id(SystemAccount::OutputVat), '15.00'),
        ],
    ));

    expect($registry->id(SystemAccount::Cash))->toEqual(Account::query()->where('code', '1110')->value('id'));
    expect($entry->lines)->toHaveCount(3);
});

it('fails loudly when a system account is missing', function (): void {
    expect(fn () => app(AccountRegistry::class)->id(SystemAccount::Cash))->toThrow(RuntimeException::class);
});
