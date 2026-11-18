<?php

declare(strict_types=1);

return [

    'name' => env('APP_NAME', 'Super Shop'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
     * The business operates in a single timezone (BDT only), so timestamps are
     * stored and displayed in Asia/Dhaka. Daily report boundaries therefore
     * match the shop's real business day.
     */
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),

    'locale' => env('APP_LOCALE', 'bn'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
