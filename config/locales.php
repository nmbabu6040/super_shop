<?php

declare(strict_types=1);

/*
 * Supported site languages. The first URL segment of every storefront route
 * is one of these codes (e.g. /bn/..., /en/...).
 */
return [

    'supported' => [
        'bn' => ['name' => 'Bangla', 'native_name' => 'বাংলা', 'direction' => 'ltr'],
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
    ],

    // Session key used to remember the visitor's chosen language.
    'session_key' => 'locale',

];
