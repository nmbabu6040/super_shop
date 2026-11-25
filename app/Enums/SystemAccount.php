<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Accounts the application itself posts to. They are found by their system key (never by a
 * code or a name an administrator may change), and they cannot be deleted or deactivated.
 */
enum SystemAccount: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileBanking = 'mobile_banking';
    case AccountsReceivable = 'accounts_receivable';
    case Inventory = 'inventory';
    case InputVat = 'input_vat';
    case AccountsPayable = 'accounts_payable';
    case OutputVat = 'output_vat';
    case CustomerWallet = 'customer_wallet';
    case SalariesPayable = 'salaries_payable';
    case RetainedEarnings = 'retained_earnings';
    case OpeningBalanceEquity = 'opening_balance_equity';
    case SalesRevenue = 'sales_revenue';
    case SalesReturns = 'sales_returns';
    case SalesDiscounts = 'sales_discounts';
    case DeliveryIncome = 'delivery_income';
    case OtherIncome = 'other_income';
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case InventoryAdjustments = 'inventory_adjustments';
    case RentExpense = 'rent_expense';
    case SalariesExpense = 'salaries_expense';
    case ElectricityExpense = 'electricity_expense';
    case InternetExpense = 'internet_expense';
    case TransportationExpense = 'transportation_expense';
    case MarketingExpense = 'marketing_expense';
    case MaintenanceExpense = 'maintenance_expense';
    case PackagingExpense = 'packaging_expense';
    case PaymentGatewayFees = 'payment_gateway_fees';
    case OtherExpenses = 'other_expenses';
}
