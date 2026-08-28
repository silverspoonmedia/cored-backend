<?php

return [

    'paths' => ['api/*'],

    // Explicit verbs only: the API uses GET/POST/PUT/PATCH/DELETE; OPTIONS is the
    // CORS preflight. A wildcard would advertise methods the API never serves.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Browser Origin allowlist: Laravel API origin (APP_URL) + Vue-Workers
    // (SPA_URL), plus any extra origins via CORS_EXTRA_ORIGINS (comma-separated;
    // preview URLs, localhost dev). The SPA calls the API directly with
    // credentials, so this list must stay explicit — a wildcard origin cannot
    // be combined with credentials. Empty CORS_EXTRA_ORIGINS = no extras.
    'allowed_origins' => array_values(array_unique(array_filter([
        rtrim((string) env('APP_URL', 'http://localhost'), '/'),
        rtrim((string) env('SPA_URL', ''), '/'),
        ...array_map(
            static fn (string $origin) => rtrim(trim($origin), '/'),
            array_filter(array_map('trim', explode(',', (string) env('CORS_EXTRA_ORIGINS', '')))),
        ),
    ]))),

    'allowed_origins_patterns' => [],

    // Explicit header list: the SPA's direct requests carry Authorization,
    // the double-submit CSRF header, and the public SPA request marker.
    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Accept-Language',
        'X-Requested-With',
        'X-CSRF-Token',
        'X-SPA-Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    // The SPA authenticates cross-site via the HttpOnly __Host-spa_jwt cookie
    // (credentials: 'include'), so the API must allow credentialed requests.
    'supports_credentials' => true,

];
