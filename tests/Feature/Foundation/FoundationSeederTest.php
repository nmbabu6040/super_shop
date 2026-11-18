<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Warehouse;
use App\Services\Settings\SettingsService;
use Database\Seeders\FoundationSeeder;

it('creates the head office, central warehouse and default settings', function (): void {
    $this->seed(FoundationSeeder::class);

    $headOffice = Branch::query()->where('code', 'HO')->first();

    expect($headOffice)->not->toBeNull();
    expect($headOffice->is_head_office)->toBeTrue();
    expect(Warehouse::query()->where('code', 'CW')->exists())->toBeTrue();

    $settings = app(SettingsService::class);
    expect($settings->get('business', 'currency_code'))->toBe('BDT');
    expect($settings->get('inventory', 'allow_negative_stock'))->toBeFalse();
});

it('can be run twice without duplicating data or overwriting changed settings', function (): void {
    $this->seed(FoundationSeeder::class);

    app(SettingsService::class)->set('general', 'site_name', 'My Custom Shop');

    $this->seed(FoundationSeeder::class);

    expect(Branch::query()->count())->toBe(1);
    // Head office shop floor + central warehouse.
    expect(Warehouse::query()->count())->toBe(2);
    expect(app(SettingsService::class)->get('general', 'site_name'))->toBe('My Custom Shop');
});
