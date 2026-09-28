<?php

/**
 * Microstock product config.
 *
 * AI metadata providers are plain HTTP endpoints (OpenAI-compatible chat
 * completions shape). Each stage (vision, thinking) is independently
 * configurable: endpoint URL, model, API key. Prompts are hardcoded in
 * App\Domain\Microstock\Support\Prompts (English, SEO-focused).
 *
 * Production stack (probe-verified 2026-07-28) — map aliases in 9Router / Open WebUI:
 *   internal.vision   → anthropic/claude-sonnet-5
 *   internal.thinking → gpt-5.6 (resolves gpt-5.6-luna)
 * Details: docs/archive/microstock-probes/sonnet5_gpt56_combo_summary.md
 */
return [
    // Dual-lane pipeline queues (see docs/domains/microstock/reference-pipeline.md#stage-decoupling-mvp--dual-lane-pipeline).
    'queues' => [
        'collection_vision' => env('MICROSTOCK_AI_COLLECTION_VISION_QUEUE', 'microstock-collection-vision'),
        'collection_thinking' => env('MICROSTOCK_AI_COLLECTION_THINKING_QUEUE', 'microstock-collection-thinking'),
        'solo' => env('MICROSTOCK_AI_SOLO_QUEUE', 'microstock-solo'),
        'collection_vision_free' => env('MICROSTOCK_AI_COLLECTION_VISION_QUEUE_FREE', 'microstock-collection-vision-free'),
        'collection_thinking_free' => env('MICROSTOCK_AI_COLLECTION_THINKING_QUEUE_FREE', 'microstock-collection-thinking-free'),
        'solo_free' => env('MICROSTOCK_AI_SOLO_QUEUE_FREE', 'microstock-solo-free'),
        // Marketplace upload lane (see config `upload` section + WorkerQueues::ORDER).
        'upload' => env('MICROSTOCK_UPLOAD_QUEUE', 'microstock-upload'),
    ],

    // Per-user daily free analyses (lazy calendar reset; no prepaid balance credit).
    'daily_free' => [
        'enabled' => false,
        'limit_per_user' => 5,
        'timezone' => 'UTC',
    ],

    'ai' => [
        // Inference provider slug for upstream cost resolution (see Inference domain).
        // Tier B — Settings → Microstock (microstock.ai.inference_provider_slug)
        'inference_provider_slug' => 'openwebui-9router',

        'vision' => [
            // Tier B — connection binding via Settings → Microstock
            // (microstock.ai.vision.connection). Empty = the default AI connection.
            'connection' => '',
            // Tier B — model / free_model / timeout via Settings → Microstock
            'model' => 'internal.vision',
            'free_model' => '',
            'timeout' => 1500,
        ],
        'thinking' => [
            'connection' => '',
            'model' => 'internal.thinking',
            'free_model' => '',
            'timeout' => 1500,
        ],

        // Longest side (px) of the downscaled preview sent to the AI vision
        // stage. The original file is never modified; the small derivative keeps
        // the gateway from hitting its ~60s cutoff on full-size images.
        'image_max_px' => 1024,

        // JPEG quality for the AI preview derivative (40-95).
        'image_jpeg_quality' => 80,

        // Cap raw JPEG bytes before base64 encoding; preview scales down until
        // under this limit to avoid token bombs on reasoning models.
        'max_request_image_bytes' => 200_000,

        // HTTP retries per AI stage (0 = no retry). Billed calls should stay at 0
        // so each provider charge maps to one usage event.
        'http_retries' => 0,

        // Multimodal thinking attempts when a preview is available (0 = text-only).
        'thinking_visual_attempts' => 2,

        // How many images are packed into one batch AI request when analyzing a
        // whole collection. Larger chunks = fewer requests but bigger payloads.
        'batch_chunk' => 10,

        // Multi-image batch requests can mis-assign metadata across assets. Below
        // this file count per chunk, run per-file vision+thinking instead.
        'batch_min_files' => 4,

        // Adaptive failure deadline per asset, measured from when analysis was
        // queued (metadata.created_at) — NOT a per-request HTTP timeout. The AI
        // stages run multi-step reasoning with no predictable duration, so the
        // deadline is phase-based instead of a fixed hard cut.
        //   normal_after: below this the asset is "analyzing".
        //   grace_after : normal..grace the asset is "degraded" (still waiting);
        //                 past grace the watchdog marks it failed.
        // grace_after MUST stay below the queue retry_after (DB_QUEUE_RETRY_AFTER
        // = 1800) so the watchdog declares failure before the queue re-dispatches
        // the still-running job.
        'phase' => [
            'normal_after' => 600,
            'grace_after' => 1500,
        ],

        // Per-stage grace deadlines for decoupled vision/thinking jobs (seconds).
        'stage' => [
            'vision_grace_after' => 600,
            'thinking_grace_after' => 1800,
            // Re-dispatch thinking when vision ok but thinking job never started (worker crash).
            'thinking_backlog_after' => 120,
        ],

        // How vision receives the image: base64 data-URI or signed HTTPS URL.
        'vision_image' => [
            'delivery' => 'url',
            // Tier B — Settings → Microstock (microstock.ai.vision_image.variant_kind)
            'variant_kind' => 'ai_1024',
            'signed_url_ttl_minutes' => 30,
        ],

        // Upstream throughput limits (shared Open WebUI / 9Router account).
        // token_budget + window_seconds are ops reference for ~20M tokens / 30 min;
        // enforcement is via concurrency caps below (see microstock README).
        'upstream' => [
            // Tier B — Settings → Microstock
            'window_seconds' => 1800,
            'token_budget' => 20_000_000,
            'max_concurrent_vision' => 6,
            'max_concurrent_thinking' => 10,
            'collection_vision_concurrency' => 3,
            'collection_thinking_concurrency' => 3,
            'solo_vision_concurrency' => 2,
            'solo_thinking_concurrency' => 2,
            'release_after_seconds' => 15,
        ],
    ],

    // Persisted JPEG derivatives generated on microstock image upload (counts toward Archivd quota).
    'variants' => [
        ['kind' => 'ai_512', 'max_px' => 512],
        ['kind' => 'ai_1024', 'max_px' => 1024],
    ],

    // Microstock collections accept only these raster image mime types; the AI
    // vision stage cannot read anything else.
    'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],

    'csv' => [
        // Columns emitted by the metadata CSV export, in order.
        'columns' => ['filename', 'title', 'description', 'category', 'keywords'],
        'keywords_separator' => ',',
    ],

    // Filesystem disk that stores generated per-collection CSV exports.
    'csv_disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Marketplace upload (decision: docs/domains/microstock/reference-marketplace-upload.md)
    |--------------------------------------------------------------------------
    |
    | CSV stays the primary export path; upload is an additive, opt-in action on
    | a review/exported collection. Everything per-marketplace lives in
    | `marketplaces`, so a new marketplace is a config entry, not a new branch.
    |
    | `columns` maps a marketplace's metadata file to our finalized fields. The
    | remote filename is always the local filename — marketplaces match rows to
    | files by name, so a rename would detach the metadata.
    */
    'upload' => [
        // Kill switch. When false the endpoint answers 422 and the SPA hides the
        // action, so a misconfigured marketplace cannot be reached by a client
        // that still has a stale build.
        'enabled' => env('MICROSTOCK_UPLOAD_ENABLED', true),

        // SFTP socket timeout (seconds) and per-batch retry budget for the job.
        'timeout' => 30,
        'tries' => 2,
        'backoff' => 60,

        // The lane name lives in `queues.upload` — the single registry
        // `WorkerQueues::ORDER` reads, so a worker actually consumes it.

        'marketplaces' => [
            'adobe_stock' => [
                'label' => 'Adobe Stock',
                'host' => env('MICROSTOCK_ADOBE_STOCK_HOST', 'sftp.contributor.adobestock.com'),
                'port' => (int) env('MICROSTOCK_ADOBE_STOCK_PORT', 22),
                'remote_path' => env('MICROSTOCK_ADOBE_STOCK_PATH', '/'),
                // Host key fingerprint, colon-separated MD5. Empty = the
                // connection is rejected, never trusted on first use.
                'host_key_fingerprint' => env('MICROSTOCK_ADOBE_STOCK_HOST_KEY', ''),
                // Manifest is a CSV whose columns the marketplace expects.
                'manifest_name' => 'metadata.csv',
                'columns' => [
                    'Filename' => 'filename',
                    'Title' => 'title',
                    'Keywords' => 'keywords',
                    'Category' => 'category',
                ],
                // Marketplace category ids by our category slug. An unmapped
                // slug falls back to `default_category`.
                'categories' => [
                    'default_category' => 1,
                ],
            ],
        ],
    ],

    // Prepaid AI analysis billing: token costs, markup, and analyze gate estimate.
    'ai_billing' => [
        'markup_multiplier' => 1.35,
        // Buffer above typical ~$0.11/asset; used by assertCanAnalyze before dispatch.
        'estimated_billable_micro_usd_per_asset' => 150_000,
        // Multiplier for gate estimate to cover multi-attempt thinking (default 1.5×).
        'estimate_thinking_attempt_factor' => 1.5,
    ],
];
