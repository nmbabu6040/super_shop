<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Notification types
 |--------------------------------------------------------------------------
 |
 | Every notification the system can send is registered here.
 | channels:   which channels the type can use ("mail", "database" = in-app).
 | mandatory:  channels a user can NOT switch off (security notices).
 | permission: only users holding this permission receive the type and see it in their
 |             notification settings (null = everyone).
 | Labels and texts live in lang/*/
//  notifications.php (types.<key>, messages.<key>).
//  */
return [
    'staff_access_changed' => [
        'channels' => ['mail', 'database'],
        'mandatory' => ['mail', 'database'],
        'permission' => null,
    ],
    'staff_invitation_accepted' => [
        'channels' => ['database'],
        'mandatory' => [],
        'permission' => 'users.view',
    ],
    'low_stock_alert' => [
        'channels' => ['mail', 'database'],
        'mandatory' => [],
        'permission' => 'inventory.view',
    ],
    'expiry_alert' => [
        'channels' => ['mail', 'database'],
        'mandatory' => [],
        'permission' => 'inventory.view',
    ],
];
