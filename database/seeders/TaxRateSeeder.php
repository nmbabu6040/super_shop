<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * Starter VAT rates. All of them are editable in the admin panel; the seeder never
 * overwrites existing rates and never changes the default once one exists.
 */
final class TaxRateSeeder extends Seeder
{
    /** [rate, English name, Bangla name, is default] */
    private const RATES = [
        ['15.00', 'VAT 15%', 'ভ্যাট ১৫%', true],
        ['10.00', 'VAT 10%', 'ভ্যাট ১০%', false],
        ['7.50', 'VAT 7.5%', 'ভ্যাট ৭.৫%', false],
        ['5.00', 'VAT 5%', 'ভ্যাট ৫%', false],
        ['0.00', 'Zero rated', 'শূন্য হার', false],
    ];

    public function run(): void
    {
        $hasDefault = TaxRate::query()->whereNotNull('default_marker')->exists();

        foreach (self::RATES as [$rate, $englishName, $banglaName, $isDefault]) {
            if (TaxRate::query()->where('name->en', $englishName)->exists()) {
                continue;
            }

            $taxRate = new TaxRate();
            $taxRate->fill(['name' => ['en' => $englishName, 'bn' => $banglaName], 'rate' => $rate, 'is_active' => true]);
            $taxRate->forceFill(['default_marker' => $isDefault && ! $hasDefault ? 1 : null])->save();
        }
    }
}
