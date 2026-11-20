<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case HeadOfficeAdmin = 'head-office-admin';
    case BranchManager = 'branch-manager';
    case AssistantManager = 'assistant-manager';
    case StoreManager = 'store-manager';
    case InventoryManager = 'inventory-manager';
    case PurchaseManager = 'purchase-manager';
    case Accountant = 'accountant';
    case Cashier = 'cashier';
    case PosOperator = 'pos-operator';
    case SalesExecutive = 'sales-executive';
    case HrManager = 'hr-manager';
    case DeliveryManager = 'delivery-manager';
    case CustomerSupport = 'customer-support';
    case WarehouseManager = 'warehouse-manager';
    case Pharmacist = 'pharmacist';
    case Customer = 'customer';

    public function label(): string
    {
        return __('roles.' . $this->value);
    }
}
