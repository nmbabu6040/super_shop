<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | RBAC catalog and default role matrix
 |--------------------------------------------------------------------------
 |
 | "modules": every permission is "<module>.<action>". Only meaningful actions
 | are listed per module. "manage" means full administrative control of that
 | module (policies accept "<module>.manage" in place of any single action).
 |
 | "roles": DEFAULT grants for each system role. Administrators may change a role
 | later; `php artisan rbac:sync` never undoes such changes (it only adds the
 | defaults of permissions that are brand new).
 |
 | Grant syntax:
 |   'grants'  => ['module' => ['view', 'create'] | '*']   ('*' = every action of the module)
 |   'revoke'  => same syntax, applied after grants/inherits
 |   'inherits'=> another role whose defaults are copied first
 |   the module key '*' applies to every module (action lists are matched leniently)
 */
return [

    'guard' => 'web',

    'modules' => [
        // System
        'users' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'roles' => ['view', 'create', 'update', 'delete', 'manage'],
        'branches' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'warehouses' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'settings' => ['view', 'update', 'manage'],
        'audit_logs' => ['view', 'export'],
        'backups' => ['view', 'create', 'delete', 'manage'],

        // Catalog
        'categories' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export'],
        'brands' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export'],
        'units' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'print', 'manage'],
        'prescriptions' => ['view', 'create', 'update', 'approve', 'reject'],

        // Inventory
        'inventory' => ['view', 'export', 'manage'],
        'stock_adjustments' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print'],
        'stock_transfers' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print', 'dispatch', 'receive'],
        'stock_counts' => ['view', 'create', 'update', 'approve', 'reject', 'export'],

        // Purchasing
        'suppliers' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'purchase_orders' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print', 'receive'],
        'purchase_returns' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print'],

        // Customers and marketing
        'customers' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'customer_groups' => ['view', 'create', 'update', 'delete'],
        'complaints' => ['view', 'create', 'update', 'delete'],
        'coupons' => ['view', 'create', 'update', 'delete', 'manage'],
        'reviews' => ['view', 'update', 'delete', 'approve', 'reject'],
        'cms' => ['view', 'create', 'update', 'delete', 'manage'],

        // Sales
        'pos' => ['view', 'create', 'apply-discount', 'process-return', 'override-price', 'open-drawer'],
        'shifts' => ['view', 'create', 'update', 'approve', 'export'],
        'orders' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print', 'manage'],
        'refunds' => ['view', 'create', 'approve', 'reject', 'export'],
        'deliveries' => ['view', 'create', 'update', 'export', 'manage'],

        // Accounting
        'accounts' => ['view', 'create', 'update', 'delete', 'manage'],
        'journal_entries' => ['view', 'create', 'approve', 'reject', 'export', 'print'],
        'expenses' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'print'],
        'incomes' => ['view', 'create', 'update', 'delete', 'export'],
        'bank_accounts' => ['view', 'create', 'update', 'delete', 'manage'],
        'reports' => ['view', 'financial', 'sales', 'inventory', 'export', 'print'],
        'documents' => ['view', 'create', 'print', 'export', 'email'],

        // HRM
        'employees' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'manage'],
        'attendance' => ['view', 'create', 'update', 'export', 'manage'],
        'leaves' => ['view', 'create', 'update', 'approve', 'reject'],
        'payroll' => ['view', 'create', 'update', 'approve', 'reject', 'export', 'print', 'manage'],
    ],

    'roles' => [

        // Unrestricted through Gate::before; also holds every permission explicitly for display.
        'super-admin' => [
            'grants' => ['*' => '*'],
        ],

        // Everything except system-owner areas: role management, backups, hard deletes, settings "manage".
        'head-office-admin' => [
            'grants' => ['*' => '*'],
            'revoke' => [
                '*' => ['force-delete'],
                'roles' => ['create', 'update', 'delete', 'manage'],
                'backups' => '*',
                'settings' => ['manage'],
            ],
        ],

        'branch-manager' => [
            'grants' => [
                'users' => ['view'],
                'employees' => ['view', 'create', 'update', 'export'],
                'branches' => ['view'],
                'warehouses' => ['view', 'update'],
                'categories' => ['view'],
                'brands' => ['view'],
                'units' => ['view'],
                'products' => ['view', 'export', 'print'],
                'prescriptions' => ['view', 'approve', 'reject'],
                'inventory' => ['view', 'export'],
                'stock_adjustments' => '*',
                'stock_transfers' => '*',
                'stock_counts' => '*',
                'suppliers' => ['view', 'create', 'update', 'export'],
                'purchase_orders' => '*',
                'purchase_returns' => '*',
                'customers' => ['view', 'create', 'update', 'export'],
                'customer_groups' => ['view'],
                'complaints' => '*',
                'coupons' => ['view'],
                'reviews' => ['view', 'approve', 'reject'],
                'pos' => '*',
                'shifts' => '*',
                'orders' => ['view', 'create', 'update', 'approve', 'reject', 'export', 'print'],
                'refunds' => '*',
                'deliveries' => ['view', 'create', 'update', 'export'],
                'expenses' => ['view', 'create', 'update', 'approve', 'reject', 'export', 'print'],
                'incomes' => ['view', 'create', 'export'],
                'journal_entries' => ['view'],
                'reports' => '*',
                'documents' => '*',
                'attendance' => ['view', 'create', 'update', 'export'],
                'leaves' => ['view', 'approve', 'reject'],
                'payroll' => ['view'],
                'audit_logs' => ['view'],
            ],
            'revoke' => ['*' => ['force-delete', 'manage']],
        ],

        // Branch manager without approvals and deletions.
        'assistant-manager' => [
            'inherits' => 'branch-manager',
            'revoke' => ['*' => ['approve', 'reject', 'delete', 'force-delete', 'manage']],
        ],

        'store-manager' => [
            'grants' => [
                'products' => ['view'],
                'categories' => ['view'],
                'brands' => ['view'],
                'units' => ['view'],
                'inventory' => ['view'],
                'stock_adjustments' => ['view', 'create'],
                'stock_transfers' => ['view', 'create', 'receive'],
                'customers' => ['view', 'create', 'update'],
                'complaints' => '*',
                'pos' => '*',
                'shifts' => ['view', 'create', 'update', 'export'],
                'orders' => ['view', 'create', 'update', 'print'],
                'refunds' => ['view', 'create'],
                'deliveries' => ['view', 'create', 'update'],
                'reviews' => ['view'],
                'coupons' => ['view'],
                'documents' => ['view', 'create', 'print', 'email'],
                'attendance' => ['view', 'create'],
                'reports' => ['view', 'sales'],
            ],
        ],

        'inventory-manager' => [
            'grants' => [
                'products' => ['view', 'create', 'update', 'export', 'print'],
                'categories' => ['view', 'create', 'update'],
                'brands' => ['view', 'create', 'update'],
                'units' => ['view'],
                'inventory' => '*',
                'stock_adjustments' => '*',
                'stock_transfers' => '*',
                'stock_counts' => '*',
                'warehouses' => ['view', 'update'],
                'suppliers' => ['view'],
                'purchase_orders' => ['view'],
                'reports' => ['view', 'inventory', 'export', 'print'],
                'documents' => ['view', 'print'],
            ],
        ],

        'purchase-manager' => [
            'grants' => [
                'suppliers' => '*',
                'purchase_orders' => '*',
                'purchase_returns' => '*',
                'products' => ['view'],
                'inventory' => ['view'],
                'stock_transfers' => ['view'],
                'expenses' => ['view', 'create'],
                'reports' => ['view', 'export'],
                'documents' => ['view', 'print', 'export'],
            ],
            'revoke' => ['*' => ['force-delete']],
        ],

        'accountant' => [
            'grants' => [
                'accounts' => '*',
                'journal_entries' => '*',
                'expenses' => '*',
                'incomes' => '*',
                'bank_accounts' => '*',
                'reports' => '*',
                'documents' => '*',
                'orders' => ['view', 'export'],
                'purchase_orders' => ['view'],
                'suppliers' => ['view'],
                'customers' => ['view'],
                'payroll' => ['view', 'export', 'print'],
                'shifts' => ['view', 'approve', 'export'],
                'refunds' => ['view', 'export'],
                'audit_logs' => ['view'],
            ],
        ],

        'cashier' => [
            'grants' => [
                'pos' => ['view', 'create', 'apply-discount', 'open-drawer'],
                'shifts' => ['view', 'create'],
                'customers' => ['view', 'create'],
                'products' => ['view'],
                'coupons' => ['view'],
                'orders' => ['view', 'create', 'print'],
                'documents' => ['view', 'print'],
            ],
        ],

        'pos-operator' => [
            'grants' => [
                'pos' => ['view', 'create'],
                'customers' => ['view', 'create'],
                'products' => ['view'],
                'orders' => ['view', 'create', 'print'],
                'documents' => ['view', 'print'],
            ],
        ],

        'sales-executive' => [
            'grants' => [
                'customers' => ['view', 'create', 'update'],
                'orders' => ['view', 'create', 'update', 'print'],
                'coupons' => ['view'],
                'products' => ['view'],
                'deliveries' => ['view'],
                'documents' => ['view', 'print', 'export'],
                'reports' => ['view', 'sales'],
            ],
        ],

        'hr-manager' => [
            'grants' => [
                'employees' => '*',
                'attendance' => '*',
                'leaves' => '*',
                'payroll' => ['view', 'create', 'update', 'approve', 'reject', 'export', 'print'],
                'users' => ['view', 'create', 'update'],
                'branches' => ['view'],
                'reports' => ['view', 'export'],
            ],
            'revoke' => ['*' => ['force-delete']],
        ],

        'delivery-manager' => [
            'grants' => [
                'deliveries' => '*',
                'orders' => ['view', 'update', 'print'],
                'customers' => ['view'],
                'reports' => ['view', 'export'],
                'documents' => ['view', 'print'],
            ],
        ],

        'customer-support' => [
            'grants' => [
                'customers' => ['view', 'update'],
                'orders' => ['view', 'update'],
                'refunds' => ['view', 'create'],
                'complaints' => '*',
                'reviews' => ['view', 'approve', 'reject'],
                'coupons' => ['view'],
                'deliveries' => ['view'],
                'prescriptions' => ['view'],
            ],
        ],

        'warehouse-manager' => [
            'grants' => [
                'warehouses' => ['view', 'update'],
                'inventory' => '*',
                'stock_adjustments' => ['view', 'create', 'update'],
                'stock_transfers' => '*',
                'stock_counts' => '*',
                'products' => ['view', 'export', 'print'],
                'suppliers' => ['view'],
                'purchase_orders' => ['view', 'receive'],
                'documents' => ['view', 'print'],
            ],
        ],

        'pharmacist' => [
            'grants' => [
                'prescriptions' => '*',
                'products' => ['view', 'export', 'print'],
                'categories' => ['view'],
                'brands' => ['view'],
                'inventory' => ['view'],
                'stock_adjustments' => ['view', 'create'],
                'customers' => ['view', 'create', 'update'],
                'pos' => ['view', 'create', 'apply-discount', 'open-drawer'],
                'orders' => ['view', 'create', 'update', 'print'],
                'documents' => ['view', 'create', 'print'],
                'reports' => ['view', 'inventory'],
            ],
        ],

        // Storefront customers hold no admin permissions; they act only on their own data.
        'customer' => [
            'grants' => [],
        ],
    ],
];
