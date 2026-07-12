<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCI KMS for envelope encryption
    |--------------------------------------------------------------------------
    |
    | When false (e.g. APP_ENV=testing), a local DEK wrapper is used so PHPUnit
    | never calls live OCI KMS. Production/staging should keep this true.
    |
    */

    'kms_enabled' => (bool) env('ENCRYPTION_KMS_ENABLED', env('APP_ENV') !== 'testing'),

    /*
    |--------------------------------------------------------------------------
    | Decrypted value cache (POC — mirrors dynamic menu cache semantics)
    |--------------------------------------------------------------------------
    |
    | TTL in seconds for caching plaintext after a successful KMS unwrap + AES
    | decrypt. When CACHE_STORE=redis in staging/production, entries move to
    | Redis automatically with the same key prefix and TTL.
    |
    */

    'decrypted_cache_ttl' => (int) env('ENCRYPTION_DECRYPTED_CACHE_TTL', 900),

    'decrypted_cache_prefix' => 'encryption.decrypted',

    /*
    |--------------------------------------------------------------------------
    | Blind index pepper
    |--------------------------------------------------------------------------
    |
    | Used for HMAC-SHA256 blind indexes (password reset tokens, future PII
    | lookup columns). Must be stable per environment; rotate with care.
    |
    */

    'encrypt_sessions' => (bool) env('ENCRYPTION_SESSION_ENABLED', true),

    'blind_index_pepper' => env('ENCRYPTION_BLIND_INDEX_PEPPER', ''),

    /*
    |--------------------------------------------------------------------------
    | Sensitive cache key prefixes
    |--------------------------------------------------------------------------
    |
    | Cache values under these key prefixes are stored as envelope JSON.
    |
    */

    'sensitive_cache_prefixes' => [
        '2fa_token:',
    ],

];
