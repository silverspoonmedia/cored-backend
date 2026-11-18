<?php

/**
 * Core AI transport config.
 *
 * Endpoints + API keys are NOT here — they live in the `ai_connections` table
 * (Tier C, envelope-encrypted) and are resolved through
 * App\Domain\Ai\Services\AiConnectionResolver. This file only carries
 * non-secret defaults for the domain itself.
 *
 * Pricing/model catalog belongs to the Inference domain — see config/inference.php.
 */
return [
    // Chat model used by the manual Test probe when a connection has no
    // `test_model` of its own. Kept empty on purpose: each connection declares
    // the model it knows how to reach.
    'default_test_model' => '',

    // Admin usage-event JSON resource. `silverspoonmedia/ai` renders the dashboard but
    // the rows come from whichever product owns the AI ledger — that product
    // publishes its resource class here in its provider. Empty = no ledger
    // installed, and the dashboard endpoint has no rows to render.
    'usage_event_resource' => '',

    // Per-consumer bindings are Tier B settings (Settings → Microstock):
    //   microstock.ai.vision.connection
    //   microstock.ai.thinking.connection
    // Empty = use the connection flagged `is_default`.

    /*
    |--------------------------------------------------------------------------
    | Local seeding (packages/ai/src/Ai/Database/Seeders/AiConnectionSeeder.php)
    |--------------------------------------------------------------------------
    |
    | Local-only wiring helper. The connection map (endpoint + API key) lives in
    | a gitignored data file; `binding_models` maps a consumer's binding setting
    | to the Tier B model setting consulted when an entry omits `test_model`.
    |
    */
    'seed' => [
        'path' => 'database/seeders/data/ai-connections.local.php',

        'binding_models' => [
            'microstock.ai.vision.connection' => 'microstock.ai.vision.model',
            'microstock.ai.thinking.connection' => 'microstock.ai.thinking.model',
        ],
    ],
];
