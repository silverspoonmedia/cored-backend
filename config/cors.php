<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Browser Origin allowlist: Blade (APP_URL) + Vue-Workers (SPA_URL).
    // Same-origin Worker traffic never hits this; it only matters if a browser
    // talks to the Laravel host directly. Empty SPA_URL is dropped.
    'allowed_origins' => array_values(array_unique(array_filter([
        rtrim((string) env('APP_URL', 'http://localhost'), '/'),
        rtrim((string) env('SPA_URL', ''), '/'),
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
