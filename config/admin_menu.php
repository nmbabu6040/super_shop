<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;

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
        'label' => 'users.menu',
        'route' => 'admin.users.index',
        'icon' => 'users',
        'can' => ['viewAny', User::class],
        'active' => 'admin.users.*',
    ],
    [
        'label' => 'settings.menu',
        'route' => 'admin.settings.index',
        'icon' => 'settings',
        'can' => ['viewAny', Setting::class],
        'active' => 'admin.settings.*',
    ],
];
