<?php

use App\Exceptions\BusinessRuleException;

return [

    /*
    |--------------------------------------------------------------------------
    | Persistent error log
    |--------------------------------------------------------------------------
    |
    | Stores reportable exceptions and Log::error+ records in the database,
    | grouped by fingerprint. Kill-switch for tests/outages without code changes.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | errorlog:clean deletes rows whose last_seen_at is older than this many days.
    |
    */

    'clean_after_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Size limits (bytes / characters)
    |--------------------------------------------------------------------------
    */

    'max_message_length' => 2000,
    'max_trace_length' => 65536,
    'max_context_json_length' => 32768,

    /*
    |--------------------------------------------------------------------------
    | Context redaction
    |--------------------------------------------------------------------------
    |
    | Keys (case-insensitive) whose values are replaced with [redacted].
    |
    */

    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'cookies',
        'secret',
        'api_key',
        'apikey',
        'private_key',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'credit_card',
        'card_number',
        'cvv',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception classes never stored
    |--------------------------------------------------------------------------
    |
    | Intentional domain / HTTP noise. Laravel already skips many 4xx types
    | before reportable callbacks; this list is an extra domain filter.
    |
    */

    'dont_store' => [
        BusinessRuleException::class,
    ],

];
