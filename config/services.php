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
    | ToyyibPay Direct Live API & Bank Credentials
    |--------------------------------------------------------------------------
    */
    'toyyibpay' => [
        'secret_key'    => env('kxmduu2g-7zzc-uysd-qry2-sk1fthtje5g8'),
        'category_code' => env('s12aui8d'),
        'base_url'      => 'https://toyyibpay.com', // LIVE ONLY
    ],

    'bank' => [
        'name'          => 'Maybank Islamic',
        'account_no'    => '563037065007',
        'account_holder'=> 'PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU',
    ],

];