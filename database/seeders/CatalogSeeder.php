<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter catalog data needed in every environment: the four product departments with their
 * usual sub-categories, and the common units of measure. Idempotent and fully editable in the
 * admin panel afterwards (existing records are never overwritten).
 */
final class CatalogSeeder extends Seeder
{
    /** @var array<string, array{en: string, bn: string, children: array<string, array{string, string}>}> */
    private const CATEGORIES = [
        'grocery' => [
            'en' => 'Grocery & Supermarket',
            'bn' => 'গ্রোসারি ও সুপারশপ',
            'children' => [
                'rice' => ['Rice', 'চাল'],
                'flour' => ['Flour', 'আটা-ময়দা'],
                'lentils' => ['Lentils', 'ডাল'],
                'cooking-oil' => ['Cooking oil', 'ভোজ্য তেল'],
                'sugar' => ['Sugar', 'চিনি'],
                'salt' => ['Salt', 'লবণ'],
                'spices' => ['Spices', 'মসলা'],
                'dairy' => ['Dairy products', 'দুগ্ধজাত পণ্য'],
                'eggs' => ['Eggs', 'ডিম'],
                'meat' => ['Meat', 'মাংস'],
                'fish' => ['Fish', 'মাছ'],
                'vegetables' => ['Vegetables', 'শাকসবজি'],
                'fruits' => ['Fruits', 'ফল'],
                'frozen-foods' => ['Frozen foods', 'ফ্রোজেন খাবার'],
                'snacks' => ['Snacks', 'স্ন্যাক্স'],
                'beverages' => ['Beverages', 'পানীয়'],
                'bakery' => ['Bakery products', 'বেকারি পণ্য'],
                'canned-foods' => ['Canned foods', 'ক্যানজাত খাবার'],
            ],
        ],
        'cosmetics' => [
            'en' => 'Cosmetics & Beauty',
            'bn' => 'কসমেটিক্স ও বিউটি',
            'children' => [
                'skincare' => ['Skincare', 'স্কিনকেয়ার'],
                'haircare' => ['Haircare', 'হেয়ারকেয়ার'],
                'makeup' => ['Makeup', 'মেকআপ'],
                'perfumes' => ['Perfumes', 'পারফিউম'],
                'personal-care' => ['Personal care', 'ব্যক্তিগত যত্ন'],
                'beauty-accessories' => ['Beauty accessories', 'বিউটি অ্যাক্সেসরিজ'],
            ],
        ],
        'medicine' => [
            'en' => 'Medicine & Pharmacy',
            'bn' => 'ওষুধ ও ফার্মেসি',
            'children' => [
                'prescription-medicines' => ['Prescription medicines', 'প্রেসক্রিপশন ওষুধ'],
                'otc-medicines' => ['OTC medicines', 'ওটিসি ওষুধ'],
                'tablets' => ['Tablets', 'ট্যাবলেট'],
                'capsules' => ['Capsules', 'ক্যাপসুল'],
                'syrups' => ['Syrups', 'সিরাপ'],
                'injections' => ['Injections', 'ইনজেকশন'],
                'medical-equipment' => ['Medical equipment', 'চিকিৎসা সরঞ্জাম'],
                'healthcare-products' => ['Healthcare products', 'স্বাস্থ্যসেবা পণ্য'],
            ],
        ],
        'hygiene' => [
            'en' => 'Hygiene & Household',
            'bn' => 'স্বাস্থ্যবিধি ও গৃহস্থালি',
            'children' => [
                'hand-wash' => ['Hand wash', 'হ্যান্ড ওয়াশ'],
                'sanitizers' => ['Sanitizers', 'স্যানিটাইজার'],
                'soap' => ['Soap', 'সাবান'],
                'shampoo' => ['Shampoo', 'শ্যাম্পু'],
                'tissue' => ['Tissue', 'টিস্যু'],
                'diapers' => ['Diapers', 'ডায়াপার'],
                'feminine-hygiene' => ['Feminine hygiene', 'নারী স্বাস্থ্যবিধি'],
                'cleaning-products' => ['Cleaning products', 'পরিষ্কারক পণ্য'],
                'detergents' => ['Detergents', 'ডিটারজেন্ট'],
                'household-essentials' => ['Household essentials', 'গৃহস্থালির প্রয়োজনীয় পণ্য'],
            ],
        ],
    ];

    /** code => [en, bn, symbol, decimal places (null = whole numbers), base code, factor] */
    private const UNITS = [
        'pcs' => ['Piece', 'পিস', 'pcs', null, null, '1'],
        'kg' => ['Kilogram', 'কেজি', 'kg', 3, null, '1'],
        'g' => ['Gram', 'গ্রাম', 'g', null, 'kg', '0.001'],
        'l' => ['Litre', 'লিটার', 'L', 3, null, '1'],
        'ml' => ['Millilitre', 'মিলিলিটার', 'ml', null, 'l', '0.001'],
        'dozen' => ['Dozen', 'ডজন', 'dz', null, 'pcs', '12'],
        'pack' => ['Pack', 'প্যাক', 'pack', null, null, '1'],
        'box' => ['Box', 'বক্স', 'box', null, null, '1'],
        'carton' => ['Carton', 'কার্টন', 'ctn', null, null, '1'],
        'bottle' => ['Bottle', 'বোতল', 'btl', null, null, '1'],
        'strip' => ['Strip', 'স্ট্রিপ', 'strip', null, null, '1'],
        'tablet' => ['Tablet', 'ট্যাবলেট', 'tab', null, null, '1'],
        'capsule' => ['Capsule', 'ক্যাপসুল', 'cap', null, null, '1'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedUnits();
            $this->seedCategories();
        });
    }

    private function seedUnits(): void
    {
        // Base units first, so derived units can point to them.
        $orderedCodes = array_merge(
            array_keys(array_filter(self::UNITS, static fn (array $unit): bool => $unit[4] === null)),
            array_keys(array_filter(self::UNITS, static fn (array $unit): bool => $unit[4] !== null)),
        );

        foreach ($orderedCodes as $code) {
            [$englishName, $banglaName, $symbol, $decimalPlaces, $baseCode, $factor] = self::UNITS[$code];

            if (Unit::query()->where('code', $code)->exists()) {
                continue;
            }

            Unit::query()->create([
                'code' => $code,
                'name' => ['en' => $englishName, 'bn' => $banglaName],
                'symbol' => $symbol,
                'is_decimal' => $decimalPlaces !== null,
                'decimal_places' => $decimalPlaces ?? 0,
                'base_unit_id' => $baseCode === null ? null : Unit::query()->where('code', $baseCode)->value('id'),
                'conversion_factor' => $factor,
                'is_active' => true,
            ]);
        }
    }

    private function seedCategories(): void
    {
        $rootOrder = 0;

        foreach (self::CATEGORIES as $rootSlug => $root) {
            $rootCategory = Category::withTrashed()->firstOrCreate(
                ['slug' => $rootSlug],
                ['name' => ['en' => $root['en'], 'bn' => $root['bn']], 'sort_order' => ++$rootOrder, 'is_active' => true]
            );

            $childOrder = 0;

            foreach ($root['children'] as $childSlug => [$englishName, $banglaName]) {
                Category::withTrashed()->firstOrCreate(
                    ['slug' => $childSlug],
                    [
                        'parent_id' => $rootCategory->getKey(),
                        'name' => ['en' => $englishName, 'bn' => $banglaName],
                        'sort_order' => ++$childOrder,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
