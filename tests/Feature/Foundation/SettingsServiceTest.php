<?php

declare(strict_types=1);

use App\Enums\SettingType;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('returns the default when a setting does not exist', function (): void {
    $settings = app(SettingsService::class);

    expect($settings->get('missing', 'nothing', 'fallback'))->toBe('fallback');
});

it('round-trips typed values', function (mixed $value, SettingType $type): void {
    $settings = app(SettingsService::class);

    $settings->set('test', 'sample', $value, $type);

    expect($settings->get('test', 'sample'))->toEqual($value);
})->with([
    'string' => ['hello', SettingType::String],
    'integer' => [42, SettingType::Integer],
    'boolean true' => [true, SettingType::Boolean],
    'boolean false' => [false, SettingType::Boolean],
    'decimal as string' => ['12.50', SettingType::Decimal],
    'json' => [['a' => 1, 'b' => ['x', 'y']], SettingType::Json],
]);

it('rejects float values', function (): void {
    app(SettingsService::class)->set('test', 'price', 12.5);
})->throws(InvalidArgumentException::class);

it('stores encrypted settings as ciphertext and reads them back in plain text', function (): void {
    $settings = app(SettingsService::class);

    $settings->set('payment', 'api_secret', 'super-secret', SettingType::String, true);

    $rawStoredValue = DB::table('settings')
        ->where('group', 'payment')
        ->where('key', 'api_secret')
        ->value('value');

    expect($rawStoredValue)->not->toBe('super-secret');
    expect($rawStoredValue)->not->toContain('super-secret');
    expect($settings->get('payment', 'api_secret'))->toBe('super-secret');
});

it('refuses to make an encrypted setting public', function (): void {
    app(SettingsService::class)->set('payment', 'api_secret', 'x', SettingType::String, true, true);
})->throws(InvalidArgumentException::class);

it('keeps an existing encrypted flag when an update does not mention it', function (): void {
    $settings = app(SettingsService::class);

    $settings->set('payment', 'api_secret', 'first', SettingType::String, true);
    $settings->set('payment', 'api_secret', 'second');

    $isEncrypted = DB::table('settings')->where('key', 'api_secret')->value('is_encrypted');

    expect((bool) $isEncrypted)->toBeTrue();
    expect($settings->get('payment', 'api_secret'))->toBe('second');
});

it('refreshes the cache when a setting changes', function (): void {
    $settings = app(SettingsService::class);

    $settings->set('general', 'site_name', 'First');
    expect($settings->get('general', 'site_name'))->toBe('First');

    $settings->set('general', 'site_name', 'Second');
    expect($settings->get('general', 'site_name'))->toBe('Second');
});

it('exposes only public non-encrypted settings to the storefront', function (): void {
    $settings = app(SettingsService::class);

    $settings->set('general', 'site_name', 'Shop', null, false, true);
    $settings->set('payment', 'api_secret', 'hidden', SettingType::String, true, false);
    $settings->set('internal', 'note', 'private');

    expect($settings->publicSettings())->toBe(['general.site_name' => 'Shop']);
});

it('never writes an encrypted setting value into the audit log', function (): void {
    app(SettingsService::class)->set('payment', 'api_secret', 'top-secret-value', SettingType::String, true);

    $auditLogJson = Activity::query()->get()->toJson();

    expect($auditLogJson)->not->toContain('top-secret-value');
});
