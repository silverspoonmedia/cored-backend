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
    | Decrypted value cache (invalidate-on-change; forever by default)
    |--------------------------------------------------------------------------
    |
    | Plaintext is cached after the first successful KMS unwrap + AES decrypt
    | per decryption context and ciphertext fingerprint. Entries are flushed on
    | encrypt() / cast set() when the value changes. decrypted_cache_max_ttl is
    | an optional safety cap (seconds); 0 = forever until forget (project default).
    | See docs/platform/cache-policy.md.
    |
    | ENCRYPTION_DECRYPTED_CACHE_TTL is a deprecated alias read when MAX_TTL is
    | unset (one-release backward compatibility).
    |
    */

    'decrypted_cache_max_ttl' => (int) env(
        'ENCRYPTION_DECRYPTED_CACHE_MAX_TTL',
        env('ENCRYPTION_DECRYPTED_CACHE_TTL', 0),
    ),

    /*
    | Per-resource_type override for decrypted plaintext cache TTL (seconds).
    | 0 = forever until forgetByContext / encrypt / cast set / explicit invalidate.
    | Keys not listed fall back to decrypted_cache_max_ttl.
    */
    'decrypted_cache_ttl_by_resource_type' => [
        // Audit-trail PII (activity_log.properties) is admin-read only and
        // immutable per row — keep plaintext in cache bounded, never forever.
        'activity_log' => 900,
    ],

    'decrypted_cache_prefix' => 'encryption.decrypted',

    /*
    |--------------------------------------------------------------------------
    | Invoice payload decrypt tracing
    |--------------------------------------------------------------------------
    |
    | When true, logs each decrypt/compare of invoice gateway_*_payload columns
    | to storage/logs/invoice-payload-decrypt.log with explicit source labels
    | (webhook, sync-status, checkout, cron, etc.).
    |
    */

    'log_invoice_payload_decrypts' => (bool) env('ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS', false),

    /*
    |--------------------------------------------------------------------------
    | Decrypt audit frequency
    |--------------------------------------------------------------------------
    |
    | cache_miss — log only the first decrypt per context + ciphertext fingerprint
    | until cache invalidation (default, fewer rows).
    | always — log every decrypt; includes cache_hit in activity properties.
    |
    */

    'audit_decrypts' => env('ENCRYPTION_AUDIT_DECRYPTS', 'cache_miss'),

    /*
    |--------------------------------------------------------------------------
    | Blind index pepper
    |--------------------------------------------------------------------------
    |
    | Used for HMAC-SHA256 blind indexes (password reset tokens, future PII
    | lookup columns). Must be stable per environment; rotate with care.
    |
    */

    'encrypt_sessions' => (bool) env('ENCRYPTION_SESSION_ENABLED', false),

    'blind_index_pepper' => env('ENCRYPTION_BLIND_INDEX_PEPPER', ''),

    /*
    |--------------------------------------------------------------------------
    | Decrypt audit exclusions (safety net)
    |--------------------------------------------------------------------------
    |
    | Sessions use Laravel SESSION_ENCRYPT (APP_KEY), not envelope KMS. If the
    | legacy encrypted_database handler is re-enabled, skip crypto activity rows.
    |
    */

    'audit_excluded_resource_types' => [
        'sessions',
        // Reading audit-trail PII must not append crypto audit rows to the
        // same trail (self-referential growth) — the trail is not a secret store.
        'activity_log',
    ],

    'audit_excluded_purposes' => [
        'session_read',
        'activity_log_read',
    ],

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
