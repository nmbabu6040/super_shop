<?php

declare(strict_types=1);

return [
    'menu' => 'Settings',
    'title' => 'Settings',
    'saved' => 'Settings saved.',
    'read_only_note' => 'You can view these settings but not change them.',
    'readonly_note' => 'This value cannot be changed here because it affects existing records.',
    'secret_saved' => 'A value is saved. Leave this empty to keep it.',
    'clear_secret' => 'Remove the saved value',
    'remove_file' => 'Remove current file',

    'groups' => [
        'general' => 'General',
        'appearance' => 'Appearance',
        'business' => 'Business',
        'inventory' => 'Inventory',
        'orders' => 'Orders',
    ],

    'fields' => [
        'general' => [
            'site_name' => 'Website name',
            'company_name' => 'Company name',
            'company_address' => 'Company address',
            'contact_phone' => 'Contact phone',
            'contact_email' => 'Contact email',
            'copyright_text' => 'Copyright text',
            'facebook_url' => 'Facebook page link',
            'instagram_url' => 'Instagram link',
            'youtube_url' => 'YouTube link',
        ],
        'appearance' => [
            'primary_color' => 'Primary colour',
            'default_theme' => 'Default theme',
            'logo_path' => 'Logo',
            'favicon_path' => 'Favicon',
        ],
        'business' => [
            'currency_code' => 'Currency',
            'currency_symbol' => 'Currency symbol',
            'currency_position' => 'Symbol position',
            'date_format' => 'Date format',
            'timezone' => 'Timezone',
            'prices_include_tax' => 'Prices include tax',
        ],
        'inventory' => [
            'allow_negative_stock' => 'Allow negative stock',
            'expiry_alert_days' => 'Expiry alert (days before)',
        ],
        'orders' => [
            'stock_reservation_minutes' => 'Stock reservation time (minutes)',
        ],
    ],

    'help' => [
        'appearance' => [
            'primary_color' => 'The other shades of the brand colour are generated automatically.',
            'logo_path' => 'JPG, PNG or WebP, up to 1 MB.',
            'favicon_path' => 'PNG or ICO, up to 256 KB.',
        ],
        'business' => [
            'prices_include_tax' => 'Changing this after sales exist affects how old prices are read. Change it only before going live.',
        ],
        'inventory' => [
            'allow_negative_stock' => 'When off, a sale can never take stock below zero.',
        ],
        'orders' => [
            'stock_reservation_minutes' => 'How long stock is held for an online customer during checkout.',
        ],
    ],

    'options' => [
        'appearance' => [
            'default_theme' => [
                'system' => 'Follow the device',
                'light' => 'Light',
                'dark' => 'Dark',
            ],
        ],
        'business' => [
            'currency_position' => [
                'before' => 'Before the amount (৳100)',
                'after' => 'After the amount (100৳)',
            ],
        ],
    ],
];
