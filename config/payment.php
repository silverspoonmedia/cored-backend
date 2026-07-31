<?php

return [
    'checkout' => [
        'prefer_channel_checkout' => filter_var(env('PAYMENT_PREFER_CHANNEL_CHECKOUT', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default buyer phone (iPaymu requires phone; User profile has no phone yet)
    |--------------------------------------------------------------------------
    */
    'default_buyer_phone' => env('PAYMENT_DEFAULT_BUYER_PHONE', '081000000000'),

    /*
    |--------------------------------------------------------------------------
    | Channel fee bearer (buyer | merchant)
    |--------------------------------------------------------------------------
    */
    'channel_fee_bearer' => env('PAYMENT_CHANNEL_FEE_BEARER', 'buyer'),

    /*
    |--------------------------------------------------------------------------
    | Reporting timezone (admin income page date filters)
    |--------------------------------------------------------------------------
    */
    'reporting_timezone' => env('PAYMENT_REPORTING_TIMEZONE', 'Asia/Jakarta'),

    'gateways' => [
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
            'notify_url' => env('MIDTRANS_NOTIFY_URL'),
            'open_api_public_key' => env('MIDTRANS_OPEN_API_PUBLIC_KEY'),
            'open_api_public_key_file' => env('MIDTRANS_OPEN_API_PUBLIC_KEY_FILE'),
            'open_api_endpoint_path' => env('MIDTRANS_OPEN_API_ENDPOINT_PATH'),
            'open_api_verify' => filter_var(env('MIDTRANS_OPEN_API_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        ],
        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'public_key' => env('XENDIT_PUBLIC_KEY'),
        ],
        'doku' => [
            'client_id' => env('DOKU_CLIENT_ID'),
            'shared_key' => env('DOKU_SHARED_KEY'),
        ],
        'ipaymu' => [
            'va' => env('IPAYMU_VA_KEY'),
            'api_key' => env('IPAYMU_API_KEY'),
            'is_production' => filter_var(env('IPAYMU_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
            'notify_url' => env('IPAYMU_NOTIFY_URL'),
        ],
    ],
];
