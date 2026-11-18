<?php

/**
 * Local AI connection seed data (TEMPLATE — commit this file only).
 *
 * Copy to `ai-connections.local.php` in the same directory (gitignored) and fill
 * in the real endpoint + API key. Then:
 *
 *     php artisan db:seed --class=AiConnectionSeeder
 *
 * The seeder is local-only (`APP_ENV=local`) and this file is the only place a
 * plaintext AI key is allowed to live on disk — the stored copy is
 * envelope-encrypted in `ai_connections` (Tier C). Never commit the filled-in
 * file: `/database/seeders/data/*.local.php` is gitignored.
 *
 * Fill a shared value once: `defaults` is inherited by every entry, and an entry
 * value always wins over it.
 *
 * @see packages/ai/src/Ai/Database/Seeders/AiConnectionSeeder.php
 * @see docs/domains/ai/README.md
 */
return [

    // Probe each connection with a 1-token request after writing it. A failed
    // probe leaves the row `pending` with `last_error_message` stamped; set
    // this to false to seed rows without touching the network.
    'probe' => true,

    /*
    |--------------------------------------------------------------------------
    | Shared transport (optional)
    |--------------------------------------------------------------------------
    |
    | Every consumer here speaks the same chat-completions endpoint with the same
    | key, so both are declared once. Null/blank means "not filled in yet" and
    | every inheriting entry is skipped with a warning — safe to run unfilled.
    |
    | Keys: endpoint, api_key, test_model, timeout_seconds,
    |       connect_timeout_seconds, max_retries, extra_headers.
    | `label` / `binds` / `is_default` are never inherited — they are per-entry.
    |
    */
    'defaults' => [
        'endpoint' => 'https://ai-chat.spnd.uk/api/chat/completions',
        'api_key' => null,
    ],

    // Keyed by connection slug. A slug that already exists is updated in place;
    // re-seeding with the same endpoint + key keeps the row `active` (no
    // re-Test needed because nothing about the identity changed).
    'connections' => [

        'microstock-vision' => [
            'label' => 'Microstock vision (local)',
            // Tier B settings bound to this slug. Leave a binding out to keep
            // the consumer on the default connection.
            'binds' => ['microstock.ai.vision.connection'],
            // First entry flagged true becomes the single default connection,
            // so consumers without a binding keep working.
            'is_default' => true,
            // `test_model` is inherited from the consumer's Tier B model setting
            // when omitted (microstock.ai.vision.model locally). Override it
            // here when this connection needs a different probe model.
            // Aliases must exist at the endpoint — list the live ones with
            // `php scripts/ai/list_models.php`, then verify with
            // `php scripts/ai/probe_models.php microstock-vision <alias>`.
            // 'test_model' => 'internal.sspn_vision',
            // 'timeout_seconds' => 300,
            // 'connect_timeout_seconds' => 10,
            // 'max_retries' => 1,
            // 'extra_headers' => [],
        ],

        'microstock-thinking' => [
            'label' => 'Microstock thinking (local)',
            'binds' => ['microstock.ai.thinking.connection'],
        ],

    ],

];
