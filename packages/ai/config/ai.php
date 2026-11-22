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

    /*
    |--------------------------------------------------------------------------
    | Consumers
    |--------------------------------------------------------------------------
    |
    | A consumer is a product-side binding: a Tier B setting that selects the
    | `ai_connections` row used for one purpose (vision, thinking, …). The
    | package names no consumer; each product publishes the rows it owns from
    | its provider, so this list is empty on a kernel-only install.
    |
    | Each row:
    |   slug               connection slug this consumer seeds locally
    |   label              human label for the seeded connection
    |   connection_setting Tier B key that binds the consumer to a connection
    |   model_setting      Tier B key consulted when a seeded entry omits test_model
    |   endpoint_env       env key holding the endpoint for the local seed
    |   api_key_env        env key holding the API key for the local seed
    |
    */
    'consumers' => [],

    /*
    |--------------------------------------------------------------------------
    | Ledger foreign keys
    |--------------------------------------------------------------------------
    |
    | The inference pricing catalog points at whichever product owns the AI
    | ledger. A product publishes the tables and columns its ledger exposes, and
    | the migration links each one only when the table already exists — a
    | kernel-only install creates the catalog and skips the link. Each row:
    |
    |   table    product-owned table
    |   columns  columns on that table that reference the catalog
    |
    */
    'ledger_fk_targets' => [],

    /*
    |--------------------------------------------------------------------------
    | Local seeding (packages/ai/src/Ai/Database/Seeders/AiConnectionSeeder.php)
    |--------------------------------------------------------------------------
    |
    | Local-only wiring helper. The connection map (endpoint + API key) lives in
    | a gitignored data file; the model for a seeded connection's Test probe is
    | inherited from the consumer row that binds it (see `consumers` above).
    |
    */
    'seed' => [
        'path' => 'database/seeders/data/ai-connections.local.php',
    ],
];
