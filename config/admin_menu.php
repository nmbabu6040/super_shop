<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/*
 | Sidebar entries. "can" is an ability checked through policies, so the sidebar and the
 | backend use the same rules. Hiding a link is only a convenience: every route is protected
 | on the server as well. "label" is a translation key; "active" is a route name pattern.
 */

return [
    [
        'label' => 'admin.menu.dashboard',
        'route' => 'admin.dashboard',
        'icon' => 'home',
        'can' => null,
        'active' => 'admin.dashboard',
    ],
    [
        'label' => 'admin.menu.branches',
        'route' => 'admin.branches.index',
        'icon' => 'briefcase',
        'can' => ['viewAny', Branch::class],
        'active' => 'admin.branches.*',
    ],
    [
        'label' => 'admin.menu.warehouses',
        'route' => 'admin.warehouses.index',
        'icon' => 'package',
        'can' => ['viewAny', Warehouse::class],
        'active' => 'admin.warehouses.*',
    ],
    [
        'label' => 'products.menu',
        'route' => 'admin.products.index',
        'icon' => 'shopping-bag',
        'can' => ['viewAny', Product::class],
        'active' => 'admin.products.*',
    ],
    [
        'label' => 'catalog.menu.categories',
        'route' => 'admin.categories.index',
        'icon' => 'layers',
        'can' => ['viewAny', Category::class],
        'active' => 'admin.categories.*',
    ],
    [
        'label' => 'catalog.menu.brands',
        'route' => 'admin.brands.index',
        'icon' => 'tag',
        'can' => ['viewAny', Brand::class],
        'active' => 'admin.brands.*',
    ],
    [
        'label' => 'catalog.menu.units',
        'route' => 'admin.units.index',
        'icon' => 'sliders',
        'can' => ['viewAny', Unit::class],
        'active' => 'admin.units.*',
    ],
    [
        'label' => 'tax.menu',
        'route' => 'admin.tax-rates.index',
        'icon' => 'percent',
        'can' => ['viewAny', TaxRate::class],
        'active' => 'admin.tax-rates.*',
    ],
    [
        'label' => 'users.menu',
        'route' => 'admin.users.index',
        'icon' => 'users',
        'can' => ['viewAny', User::class],
        'active' => 'admin.users.*',
    ],
    [
        'label' => 'roles.menu',
        'route' => 'admin.roles.index',
        'icon' => 'shield',
        'can' => ['viewAny', Role::class],
        'active' => 'admin.roles.*',
    ],
    [
        'label' => 'audit.menu',
        'route' => 'admin.audit-logs.index',
        'icon' => 'list',
        'can' => ['viewAny', Activity::class],
        'active' => 'admin.audit-logs.*',
    ],
    [
        'label' => 'settings.menu',
        'route' => 'admin.settings.index',
        'icon' => 'settings',
        'can' => ['viewAny', Setting::class],
        'active' => 'admin.settings.*',
    ],
];
