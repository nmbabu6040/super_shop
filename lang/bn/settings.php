<?php

declare(strict_types=1);

return [
    'menu' => 'সেটিংস',
    'title' => 'সেটিংস',
    'saved' => 'সেটিংস সংরক্ষণ হয়েছে।',
    'read_only_note' => 'আপনি এই সেটিংস দেখতে পারবেন, কিন্তু বদলাতে পারবেন না।',
    'readonly_note' => 'বিদ্যমান রেকর্ডে প্রভাব পড়ে বলে এই মান এখানে বদলানো যায় না।',
    'secret_saved' => 'একটি মান সংরক্ষিত আছে। রেখে দিতে চাইলে ঘরটি খালি রাখুন।',
    'clear_secret' => 'সংরক্ষিত মান সরিয়ে ফেলুন',
    'remove_file' => 'বর্তমান ফাইল সরিয়ে ফেলুন',

    'groups' => [
        'general' => 'সাধারণ',
        'appearance' => 'চেহারা',
        'business' => 'ব্যবসা',
        'inventory' => 'ইনভেন্টরি',
        'orders' => 'অর্ডার',
    ],

    'fields' => [
        'general' => [
            'site_name' => 'ওয়েবসাইটের নাম',
            'company_name' => 'প্রতিষ্ঠানের নাম',
            'company_address' => 'প্রতিষ্ঠানের ঠিকানা',
            'contact_phone' => 'যোগাযোগের ফোন',
            'contact_email' => 'যোগাযোগের ইমেইল',
            'copyright_text' => 'কপিরাইট লেখা',
            'facebook_url' => 'ফেসবুক পেজের লিংক',
            'instagram_url' => 'ইনস্টাগ্রামের লিংক',
            'youtube_url' => 'ইউটিউবের লিংক',
        ],
        'appearance' => [
            'primary_color' => 'প্রাথমিক রঙ',
            'default_theme' => 'ডিফল্ট থিম',
            'logo_path' => 'লোগো',
            'favicon_path' => 'ফেভিকন',
        ],
        'business' => [
            'currency_code' => 'মুদ্রা',
            'currency_symbol' => 'মুদ্রার চিহ্ন',
            'currency_position' => 'চিহ্নের অবস্থান',
            'date_format' => 'তারিখের ফরম্যাট',
            'timezone' => 'টাইমজোন',
            'prices_include_tax' => 'দামের মধ্যে ট্যাক্স ধরা আছে',
        ],
        'inventory' => [
            'allow_negative_stock' => 'ঋণাত্মক স্টক অনুমোদন',
            'expiry_alert_days' => 'মেয়াদ শেষের সতর্কতা (কত দিন আগে)',
        ],
        'orders' => [
            'stock_reservation_minutes' => 'স্টক সংরক্ষণের সময় (মিনিট)',
        ],
    ],

    'help' => [
        'appearance' => [
            'primary_color' => 'ব্র্যান্ড রঙের বাকি শেডগুলো নিজে থেকে তৈরি হয়।',
            'logo_path' => 'JPG, PNG বা WebP, সর্বোচ্চ ১ MB।',
            'favicon_path' => 'PNG বা ICO, সর্বোচ্চ ২৫৬ KB।',
        ],
        'business' => [
            'prices_include_tax' => 'বিক্রয় শুরু হওয়ার পরে এটি বদলালে পুরনো দামের অর্থ বদলে যায়। শুধু চালুর আগেই বদলান।',
        ],
        'inventory' => [
            'allow_negative_stock' => 'বন্ধ থাকলে কোনো বিক্রয় স্টক শূন্যের নিচে নামাতে পারে না।',
        ],
        'orders' => [
            'stock_reservation_minutes' => 'চেকআউটের সময় অনলাইন গ্রাহকের জন্য স্টক কতক্ষণ ধরে রাখা হবে।',
        ],
    ],

    'options' => [
        'appearance' => [
            'default_theme' => [
                'system' => 'ডিভাইসের সাথে মিলিয়ে',
                'light' => 'লাইট',
                'dark' => 'ডার্ক',
            ],
        ],
        'business' => [
            'currency_position' => [
                'before' => 'অঙ্কের আগে (৳100)',
                'after' => 'অঙ্কের পরে (100৳)',
            ],
        ],
    ],
];
