<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway enforcement
    |--------------------------------------------------------------------------
    |
    | When true, API requests must include a valid X-Gateway-Secret header or
    | originate from an allowlisted IP. Disable locally so Blade and tests work.
    |
    */

    'enforce' => (bool) env('GATEWAY_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Shared secret
    |--------------------------------------------------------------------------
    |
    | Must match MONOLITH_GATEWAY_SECRET on the public gateway application.
    |
    */

    'secret' => env('GATEWAY_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Allowed gateway IPs
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of IPs that may call the API without the secret.
    |
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GATEWAY_ALLOWED_IPS', '')),
    ))),

];
