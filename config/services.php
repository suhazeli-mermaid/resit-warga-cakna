<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ToyyibPay Direct API & Bank Credentials
    |--------------------------------------------------------------------------
    */
    'toyyibpay' => [
        'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        'base_url' => env('TOYYIBPAY_BASE_URL'),
    ],

    'notifications' => [
        'admin_email' => env('ADMIN_NOTIFICATION_EMAIL'),
    ],

    'bank' => [
        'name' => 'Maybank Islamic',
        'account_no' => '563037065007',
        'account_holder' => 'PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU',
    ],

];
