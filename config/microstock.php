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
 * Details: docs/domains/microstock/probes/sonnet5_gpt56_combo_summary.md
 */
return [
    // Dual-lane pipeline queues (see docs/domains/microstock/README.md#operations-queue-worker).
    'queues' => [
        'collection_vision' => env('MICROSTOCK_AI_COLLECTION_VISION_QUEUE', 'microstock-collection-vision'),
        'collection_thinking' => env('MICROSTOCK_AI_COLLECTION_THINKING_QUEUE', 'microstock-collection-thinking'),
        'solo' => env('MICROSTOCK_AI_SOLO_QUEUE', 'microstock-solo'),
    ],

    'ai' => [
        'vision' => [
            'endpoint' => env('MICROSTOCK_AI_VISION_ENDPOINT', 'https://ai-chat.spnd.uk/api/chat/completions'),
            'model' => env('MICROSTOCK_AI_VISION_MODEL', 'internal.vision'),
            'api_key' => env('MICROSTOCK_AI_VISION_API_KEY'),
            'timeout' => (int) env('MICROSTOCK_AI_VISION_TIMEOUT', 1500),
        ],
        'thinking' => [
            'endpoint' => env('MICROSTOCK_AI_THINKING_ENDPOINT', 'https://ai-chat.spnd.uk/api/chat/completions'),
            'model' => env('MICROSTOCK_AI_THINKING_MODEL', 'internal.thinking'),
            'api_key' => env('MICROSTOCK_AI_THINKING_API_KEY'),
            'timeout' => (int) env('MICROSTOCK_AI_THINKING_TIMEOUT', 1500),
        ],

        // Longest side (px) of the downscaled preview sent to the AI vision
        // stage. The original file is never modified; the small derivative keeps
        // the gateway from hitting its ~60s cutoff on full-size images.
        'image_max_px' => (int) env('MICROSTOCK_AI_IMAGE_MAX_PX', 1024),

        // JPEG quality for the AI preview derivative (40-95).
        'image_jpeg_quality' => (int) env('MICROSTOCK_AI_IMAGE_JPEG_QUALITY', 80),

        // Cap raw JPEG bytes before base64 encoding; preview scales down until
        // under this limit to avoid token bombs on reasoning models.
        'max_request_image_bytes' => (int) env('MICROSTOCK_AI_MAX_REQUEST_IMAGE_BYTES', 200_000),

        // HTTP retries per AI stage (0 = no retry). Billed calls should stay at 0
        // so each provider charge maps to one usage event.
        'http_retries' => (int) env('MICROSTOCK_AI_HTTP_RETRIES', 0),

        // Multimodal thinking attempts when a preview is available (0 = text-only).
        'thinking_visual_attempts' => (int) env('MICROSTOCK_AI_THINKING_VISUAL_ATTEMPTS', 2),

        // How many images are packed into one batch AI request when analyzing a
        // whole collection. Larger chunks = fewer requests but bigger payloads.
        'batch_chunk' => (int) env('MICROSTOCK_AI_BATCH_CHUNK', 10),

        // Multi-image batch requests can mis-assign metadata across assets. Below
        // this file count per chunk, run per-file vision+thinking instead.
        'batch_min_files' => (int) env('MICROSTOCK_AI_BATCH_MIN_FILES', 4),

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
            'normal_after' => (int) env('MICROSTOCK_AI_PHASE_NORMAL', 600),
            'grace_after' => (int) env('MICROSTOCK_AI_PHASE_GRACE', 1500),
        ],

        // Per-stage grace deadlines for decoupled vision/thinking jobs (seconds).
        'stage' => [
            'vision_grace_after' => (int) env('MICROSTOCK_AI_VISION_GRACE_AFTER', 600),
            'thinking_grace_after' => (int) env('MICROSTOCK_AI_THINKING_GRACE_AFTER', 1800),
            // Re-dispatch thinking when vision ok but thinking job never started (worker crash).
            'thinking_backlog_after' => (int) env('MICROSTOCK_AI_THINKING_BACKLOG_AFTER', 120),
        ],

        // How vision receives the image: base64 data-URI or signed HTTPS URL.
        'vision_image' => [
            'delivery' => env('MICROSTOCK_AI_VISION_IMAGE_DELIVERY', 'url'),
            'variant_kind' => env('MICROSTOCK_AI_VISION_VARIANT', 'ai_1024'),
            'signed_url_ttl_minutes' => (int) env('MICROSTOCK_AI_VISION_URL_TTL', 30),
        ],
    ],

    // Persisted JPEG derivatives generated on microstock image upload (counts toward FH quota).
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
    'csv_disk' => env('MICROSTOCK_CSV_DISK', 'local'),

    // Prepaid AI analysis billing: token costs, markup, and analyze gate estimate.
    'ai_billing' => [
        'markup_multiplier' => (float) env('MICROSTOCK_AI_BILLING_MARKUP', 1.35),
        // Buffer above typical ~$0.11/asset; used by assertCanAnalyze before dispatch.
        'estimated_billable_micro_usd_per_asset' => (int) env('MICROSTOCK_AI_BILLING_ESTIMATE_PER_ASSET', 150_000),
        // Multiplier for gate estimate to cover multi-attempt thinking (default 1.5×).
        'estimate_thinking_attempt_factor' => (float) env('MICROSTOCK_AI_BILLING_ESTIMATE_FACTOR', 1.5),
        'models' => [
            'grok-grok-4.5-high' => [
                'input_per_million_usd' => 3.0,
                'output_per_million_usd' => 15.0,
            ],
            'gpt-5.4' => [
                'input_per_million_usd' => 2.5,
                'output_per_million_usd' => 15.0,
            ],
            'kimi-k3' => [
                'input_per_million_usd' => 3.0,
                'output_per_million_usd' => 15.0,
            ],
            'gpt-5.6' => [
                'input_per_million_usd' => 5.0,
                'output_per_million_usd' => 30.0,
            ],
            'gpt-5.6-sol' => [
                'input_per_million_usd' => 5.0,
                'output_per_million_usd' => 30.0,
            ],
            'gpt-5.6-terra' => [
                'input_per_million_usd' => 2.5,
                'output_per_million_usd' => 15.0,
            ],
            'gpt-5.6-luna' => [
                'input_per_million_usd' => 1.0,
                'output_per_million_usd' => 6.0,
            ],
            'claude-opus-5' => [
                'input_per_million_usd' => 5.0,
                'output_per_million_usd' => 25.0,
            ],
            'anthropic/claude-opus-5' => [
                'input_per_million_usd' => 5.0,
                'output_per_million_usd' => 25.0,
            ],
            'claude-sonnet-5' => [
                'input_per_million_usd' => 2.0,
                'output_per_million_usd' => 10.0,
            ],
            'anthropic/claude-sonnet-5' => [
                'input_per_million_usd' => 2.0,
                'output_per_million_usd' => 10.0,
            ],
            'default' => [
                'input_per_million_usd' => 5.0,
                'output_per_million_usd' => 20.0,
            ],
        ],
    ],
];
