<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Setting;
use App\Services\Settings\SettingsFormService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
});

it('shows the settings pages to users with the settings permission', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/settings')
        ->assertRedirect(route('admin.settings.edit', 'general'));

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/settings/general')
        ->assertOk()
        ->assertSee(__('settings.title'));
});

it('forbids users without the settings permission', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))
        ->get('/admin/settings/general')
        ->assertForbidden();
});

it('lets a view-only user read the settings but not save them', function (): void {
    $viewer = createStaffWithRole(RoleName::Cashier);
    $viewer->givePermissionTo('settings.view');

    $this->actingAs($viewer)->get('/admin/settings/general')->assertOk();

    $this->actingAs($viewer)
        ->put('/admin/settings/general', ['site_name' => 'Hacked'])
        ->assertForbidden();
});

it('saves general settings and marks the public ones', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/general', [
            'site_name' => 'Fresh Mart',
            'company_name' => 'Fresh Mart Ltd',
            'contact_email' => 'hello@freshmart.test',
            'facebook_url' => 'https://facebook.com/freshmart',
        ])
        ->assertRedirect(route('admin.settings.edit', 'general'));

    $settings = app(SettingsService::class);

    expect($settings->get('general', 'site_name'))->toBe('Fresh Mart');
    expect($settings->get('general', 'company_name'))->toBe('Fresh Mart Ltd');
    expect($settings->publicSettings())->toHaveKey('general.site_name');
    expect($settings->publicSettings())->not->toHaveKey('general.company_name');
});

it('ignores settings that are not declared in the schema', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/general', ['site_name' => 'Fresh Mart', 'evil_key' => 'x']);

    expect(Setting::query()->where('key', 'evil_key')->exists())->toBeFalse();
});

it('does not let read-only settings be changed', function (): void {
    app(SettingsService::class)->set('business', 'currency_code', 'BDT');

    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/business', [
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'currency_symbol' => '৳',
            'currency_position' => 'before',
            'date_format' => 'd M Y',
        ]);

    expect(app(SettingsService::class)->get('business', 'currency_code'))->toBe('BDT');
    expect(app(SettingsService::class)->get('business', 'timezone', 'Asia/Dhaka'))->toBe('Asia/Dhaka');
});

it('validates settings values', function (array $payload, string $invalidField): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/appearance', array_merge(['primary_color' => '#0f9179', 'default_theme' => 'system'], $payload))
        ->assertSessionHasErrors($invalidField);
})->with([
    'css injection in colour' => [['primary_color' => '#000;} body{display:none'], 'primary_color'],
    'short colour' => [['primary_color' => '#fff'], 'primary_color'],
    'unknown theme' => [['default_theme' => 'neon'], 'default_theme'],
]);

it('stores the checkbox settings as booleans', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/inventory', ['allow_negative_stock' => '1', 'expiry_alert_days' => '90']);

    $settings = app(SettingsService::class);

    expect($settings->get('inventory', 'allow_negative_stock'))->toBeTrue();
    expect($settings->get('inventory', 'expiry_alert_days'))->toBe(90);

    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/inventory', ['allow_negative_stock' => '0', 'expiry_alert_days' => '90']);

    expect(app(SettingsService::class)->get('inventory', 'allow_negative_stock'))->toBeFalse();
});

it('applies the chosen primary colour to every page', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put('/admin/settings/appearance', ['primary_color' => '#aa3300', 'default_theme' => 'dark']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('--brand-500: #aa3300;', false)
        ->assertSee('--brand-600: #', false);
});

it('stores and removes the logo and favicon', function (): void {
    Storage::fake('public');
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->put('/admin/settings/appearance', [
        'primary_color' => '#0f9179',
        'default_theme' => 'system',
        'logo_path' => UploadedFile::fake()->image('logo.png', 120, 120),
        'favicon_path' => UploadedFile::fake()->image('favicon.png', 32, 32),
    ]);

    $logoPath = app(SettingsService::class)->get('appearance', 'logo_path');

    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);

    $this->actingAs($admin)->put('/admin/settings/appearance', [
        'primary_color' => '#0f9179',
        'default_theme' => 'system',
        'remove_logo_path' => '1',
    ]);

    expect(app(SettingsService::class)->get('appearance', 'logo_path'))->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

it('rejects unsafe logo uploads', function (): void {
    Storage::fake('public');

    $this->actingAs(createHeadOfficeAdmin())
        ->put('/admin/settings/appearance', [
            'primary_color' => '#0f9179',
            'default_theme' => 'system',
            'logo_path' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo_path');
});

it('handles secret settings: encrypted, never shown, blank keeps, only managers may change', function (): void {
    config(['settings_schema.groups.vault' => ['fields' => [
        'api_key' => ['type' => 'string', 'input' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
    ]]]);

    $service = app(SettingsFormService::class);

    $regularAdmin = createStaffWithRole(RoleName::HeadOfficeAdmin);
    $secretManager = createStaffWithRole(RoleName::HeadOfficeAdmin);
    $secretManager->givePermissionTo('settings.manage');

    // Without "settings.manage" a secret cannot be written.
    $service->update($regularAdmin, 'vault', ['api_key' => 'sneaky']);
    expect($service->valuesFor('vault')['api_key']['has_secret'])->toBeFalse();

    // A manager saves it: stored encrypted, and the form only learns that a value exists.
    $service->update($secretManager, 'vault', ['api_key' => 'live-secret-123']);

    $rawStoredValue = DB::table('settings')->where('group', 'vault')->where('key', 'api_key')->value('value');

    expect($rawStoredValue)->not->toContain('live-secret-123');
    expect(app(SettingsService::class)->get('vault', 'api_key'))->toBe('live-secret-123');
    expect($service->valuesFor('vault')['api_key'])->toBe(['value' => null, 'has_secret' => true]);

    // A blank field keeps the stored secret.
    $service->update($secretManager, 'vault', ['api_key' => '']);
    expect(app(SettingsService::class)->get('vault', 'api_key'))->toBe('live-secret-123');

    // An explicit "clear" removes it.
    $service->update($secretManager, 'vault', ['clear_api_key' => '1']);
    expect($service->valuesFor('vault')['api_key']['has_secret'])->toBeFalse();
});
