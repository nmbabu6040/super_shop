<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Admin-editable settings (whitelist)
 |--------------------------------------------------------------------------
 |
 | Labels come from lang/*/
//  settings.php ("fields.<group>.<key>", "help.<group>.<key>").
//  | field options: type (string|integer|boolean|decimal|json), input (text|email|url|number|select|
//  | checkbox|color|file|secret), rules, public (may be shown on the storefront), readonly,
//  | default, options (for select). "secret" fields are stored encrypted, never sent back to the
//  | browser, and can be changed only with the "settings.manage" permission.
//  */


return [
    'groups' => [
        'general' => [
            'fields' => [
                'site_name' => ['rules' => ['required', 'string', 'max:100'], 'public' => true, 'default' => 'Super Shop'],
                'company_name' => ['rules' => ['nullable', 'string', 'max:150']],
                'company_address' => ['rules' => ['nullable', 'string', 'max:255']],
                'contact_phone' => ['rules' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'], 'public' => true],
                'contact_email' => ['input' => 'email', 'rules' => ['nullable', 'string', 'email', 'max:255'], 'public' => true],
                'copyright_text' => ['rules' => ['nullable', 'string', 'max:200'], 'public' => true],
                'facebook_url' => ['input' => 'url', 'rules' => ['nullable', 'url:http,https', 'max:255'], 'public' => true],
                'instagram_url' => ['input' => 'url', 'rules' => ['nullable', 'url:http,https', 'max:255'], 'public' => true],
                'youtube_url' => ['input' => 'url', 'rules' => ['nullable', 'url:http,https', 'max:255'], 'public' => true],
            ],
        ],

        'appearance' => [
            'fields' => [
                'primary_color' => ['input' => 'color', 'rules' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'public' => true, 'default' => '#0f9179'],
                'default_theme' => ['input' => 'select', 'options' => ['system', 'light', 'dark'], 'rules' => ['required', 'in:system,light,dark'], 'public' => true, 'default' => 'system'],
                'logo_path' => ['input' => 'file', 'rules' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'], 'public' => true],
                'favicon_path' => ['input' => 'file', 'rules' => ['nullable', 'file', 'mimes:png,ico', 'max:256'], 'public' => true],
            ],
        ],

        'business' => [
            'fields' => [
                // Changing the currency or timezone after transactions exist would corrupt history.
                'currency_code' => ['readonly' => true, 'default' => 'BDT', 'public' => true],
                'currency_symbol' => ['rules' => ['required', 'string', 'max:5'], 'public' => true, 'default' => '৳'],
                'currency_position' => ['input' => 'select', 'options' => ['before', 'after'], 'rules' => ['required', 'in:before,after'], 'public' => true, 'default' => 'before'],
                'date_format' => ['input' => 'select', 'options' => ['d M Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'], 'option_labels' => 'date', 'rules' => ['required', 'in:d M Y,d/m/Y,d-m-Y,Y-m-d'], 'public' => true, 'default' => 'd M Y'],
                'timezone' => ['readonly' => true, 'default' => 'Asia/Dhaka'],
                'prices_include_tax' => ['type' => 'boolean', 'input' => 'checkbox', 'public' => true, 'default' => true],
            ],
        ],

        'inventory' => [
            'fields' => [
                'allow_negative_stock' => ['type' => 'boolean', 'input' => 'checkbox', 'default' => false],
                'expiry_alert_days' => ['type' => 'integer', 'input' => 'number', 'rules' => ['required', 'integer', 'between:1,365'], 'default' => 60],
            ],
        ],

        'orders' => [
            'fields' => [
                'stock_reservation_minutes' => ['type' => 'integer', 'input' => 'number', 'rules' => ['required', 'integer', 'between:5,120'], 'default' => 15],
            ],
        ],
    ],
];
