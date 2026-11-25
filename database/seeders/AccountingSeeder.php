<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A starter chart of accounts (including every account the application posts to) and a first
 * fiscal year. Idempotent: existing accounts are never overwritten and a fiscal year is only
 * created when none exists. Everything stays editable in the admin panel.
 */
final class AccountingSeeder extends Seeder
{
    /** [code, English name, Bangla name, type, postable, parent code, system key] */
    private const CHART = [
        ['1000', 'Assets', 'সম্পদ', 'asset', false, null, null],
        ['1100', 'Current assets', 'চলতি সম্পদ', 'asset', false, '1000', null],
        ['1110', 'Cash in hand', 'হাতে নগদ', 'asset', true, '1100', 'cash'],
        ['1120', 'Bank accounts', 'ব্যাংক হিসাব', 'asset', true, '1100', 'bank'],
        ['1130', 'Mobile banking', 'মোবাইল ব্যাংকিং', 'asset', true, '1100', 'mobile_banking'],
        ['1140', 'Accounts receivable', 'প্রাপ্য হিসাব', 'asset', true, '1100', 'accounts_receivable'],
        ['1150', 'Inventory', 'মজুত পণ্য', 'asset', true, '1100', 'inventory'],
        ['1160', 'Input VAT', 'ইনপুট ভ্যাট', 'asset', true, '1100', 'input_vat'],

        ['2000', 'Liabilities', 'দায়', 'liability', false, null, null],
        ['2100', 'Current liabilities', 'চলতি দায়', 'liability', false, '2000', null],
        ['2110', 'Accounts payable', 'প্রদেয় হিসাব', 'liability', true, '2100', 'accounts_payable'],
        ['2120', 'Output VAT payable', 'আউটপুট ভ্যাট প্রদেয়', 'liability', true, '2100', 'output_vat'],
        ['2130', 'Customer wallet balances', 'গ্রাহক ওয়ালেট ব্যালেন্স', 'liability', true, '2100', 'customer_wallet'],
        ['2140', 'Salaries payable', 'বেতন প্রদেয়', 'liability', true, '2100', 'salaries_payable'],

        ['3000', 'Equity', 'মালিকানা স্বত্ব', 'equity', false, null, null],
        ['3100', "Owner's capital", 'মালিকের মূলধন', 'equity', true, '3000', null],
        ['3200', 'Retained earnings', 'সঞ্চিত মুনাফা', 'equity', true, '3000', 'retained_earnings'],
        ['3300', 'Opening balance equity', 'প্রারম্ভিক জের ইক্যুইটি', 'equity', true, '3000', 'opening_balance_equity'],

        ['4000', 'Revenue', 'আয়', 'revenue', false, null, null],
        ['4100', 'Sales revenue', 'বিক্রয় আয়', 'revenue', true, '4000', 'sales_revenue'],
        ['4200', 'Sales returns', 'বিক্রয় ফেরত', 'revenue', true, '4000', 'sales_returns'],
        ['4300', 'Sales discounts', 'বিক্রয় ছাড়', 'revenue', true, '4000', 'sales_discounts'],
        ['4400', 'Delivery income', 'ডেলিভারি আয়', 'revenue', true, '4000', 'delivery_income'],
        ['4900', 'Other income', 'অন্যান্য আয়', 'revenue', true, '4000', 'other_income'],

        ['5000', 'Cost of sales', 'বিক্রয় ব্যয়', 'expense', false, null, null],
        ['5100', 'Cost of goods sold', 'বিক্রীত পণ্যের ব্যয়', 'expense', true, '5000', 'cost_of_goods_sold'],
        ['5200', 'Inventory adjustments', 'মজুত সমন্বয় ও অপচয়', 'expense', true, '5000', 'inventory_adjustments'],

        ['6000', 'Operating expenses', 'পরিচালন ব্যয়', 'expense', false, null, null],
        ['6100', 'Rent', 'ভাড়া', 'expense', true, '6000', 'rent_expense'],
        ['6200', 'Salaries', 'বেতন', 'expense', true, '6000', 'salaries_expense'],
        ['6300', 'Electricity', 'বিদ্যুৎ', 'expense', true, '6000', 'electricity_expense'],
        ['6400', 'Internet', 'ইন্টারনেট', 'expense', true, '6000', 'internet_expense'],
        ['6500', 'Transportation', 'পরিবহন', 'expense', true, '6000', 'transportation_expense'],
        ['6600', 'Marketing', 'বিপণন', 'expense', true, '6000', 'marketing_expense'],
        ['6700', 'Maintenance', 'রক্ষণাবেক্ষণ', 'expense', true, '6000', 'maintenance_expense'],
        ['6800', 'Packaging', 'প্যাকেজিং', 'expense', true, '6000', 'packaging_expense'],
        ['6850', 'Payment gateway fees', 'পেমেন্ট গেটওয়ে ফি', 'expense', true, '6000', 'payment_gateway_fees'],
        ['6900', 'Other expenses', 'অন্যান্য ব্যয়', 'expense', true, '6000', 'other_expenses'],
    ];

    public function run(FiscalCalendarService $fiscalCalendar): void
    {
        DB::transaction(function () use ($fiscalCalendar): void {
            foreach (self::CHART as [$code, $englishName, $banglaName, $type, $isPostable, $parentCode, $systemKey]) {
                if (Account::query()->where('code', $code)->exists()) {
                    continue;
                }

                $account = new Account();
                $account->fill([
                    'parent_id' => $parentCode === null ? null : Account::query()->where('code', $parentCode)->value('id'),
                    'code' => $code,
                    'name' => ['en' => $englishName, 'bn' => $banglaName],
                    'type' => AccountType::from($type),
                    'is_postable' => $isPostable,
                    'is_active' => true,
                ]);
                // The system key is not mass assignable: only this seeder sets it.
                $account->forceFill(['system_key' => $systemKey])->save();
            }

            if (FiscalYear::query()->doesntExist()) {
                $year = now()->year;

                $fiscalCalendar->createFiscalYear('FY ' . $year, CarbonImmutable::create($year, 1, 1));
            }
        });
    }
}
