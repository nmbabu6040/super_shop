<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\SettingType;
use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bootstrap data the system needs in EVERY environment (including production):
 * the head office, a central warehouse and sensible default settings.
 * It is idempotent: running it again never overwrites values an administrator changed.
 */
final class FoundationSeeder extends Seeder
{
    private const DEFAULT_SETTINGS = [
        ['group' => 'general', 'key' => 'site_name', 'value' => 'Super Shop', 'type' => SettingType::String, 'public' => true],
        ['group' => 'business', 'key' => 'currency_code', 'value' => 'BDT', 'type' => SettingType::String, 'public' => true],
        ['group' => 'business', 'key' => 'currency_symbol', 'value' => '৳', 'type' => SettingType::String, 'public' => true],
        ['group' => 'business', 'key' => 'currency_position', 'value' => 'before', 'type' => SettingType::String, 'public' => true],
        ['group' => 'business', 'key' => 'date_format', 'value' => 'd M Y', 'type' => SettingType::String, 'public' => true],
        ['group' => 'business', 'key' => 'timezone', 'value' => 'Asia/Dhaka', 'type' => SettingType::String, 'public' => false],
        ['group' => 'business', 'key' => 'prices_include_tax', 'value' => true, 'type' => SettingType::Boolean, 'public' => true],
        ['group' => 'inventory', 'key' => 'allow_negative_stock', 'value' => false, 'type' => SettingType::Boolean, 'public' => false],
        ['group' => 'inventory', 'key' => 'expiry_alert_days', 'value' => 60, 'type' => SettingType::Integer, 'public' => false],
        ['group' => 'orders', 'key' => 'stock_reservation_minutes', 'value' => 15, 'type' => SettingType::Integer, 'public' => false],
    ];

    public function run(SettingsService $settingsService): void
    {
        DB::transaction(function () use ($settingsService): void {
            Branch::query()->firstOrCreate(
                ['code' => 'HO'],
                [
                    'name' => 'Head Office',
                    'is_head_office' => true,
                    'status' => BranchStatus::Active,
                    'opening_balance' => 0,
                ]
            );

            Warehouse::query()->firstOrCreate(
                ['code' => 'CW'],
                [
                    'name' => 'Central Warehouse',
                    'branch_id' => null,
                    'type' => WarehouseType::Central,
                    'is_sellable' => false,
                    'is_active' => true,
                ]
            );

            foreach (self::DEFAULT_SETTINGS as $definition) {
                $alreadyConfigured = Setting::query()
                    ->where('group', $definition['group'])
                    ->where('key', $definition['key'])
                    ->exists();

                if (! $alreadyConfigured) {
                    $settingsService->set(
                        $definition['group'],
                        $definition['key'],
                        $definition['value'],
                        $definition['type'],
                        false,
                        $definition['public'],
                    );
                }
            }
        });
    }
}
