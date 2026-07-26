<?php

/**
 * Microstock product config.
 *
 * AI metadata providers are plain HTTP endpoints (OpenAI-compatible chat
 * completions shape). Each stage (vision, thinking) is independently
 * configurable: endpoint URL, model, API key. Prompts are hardcoded in
 * App\Domain\Microstock\Support\Prompts (English, SEO-focused).
 */
return [
    'ai' => [
        'vision' => [
            'endpoint' => env('MICROSTOCK_AI_VISION_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'model' => env('MICROSTOCK_AI_VISION_MODEL', 'gpt-4o'),
            'api_key' => env('MICROSTOCK_AI_VISION_API_KEY'),
            'timeout' => (int) env('MICROSTOCK_AI_VISION_TIMEOUT', 60),
        ],
        'thinking' => [
            'endpoint' => env('MICROSTOCK_AI_THINKING_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'model' => env('MICROSTOCK_AI_THINKING_MODEL', 'gpt-4o'),
            'api_key' => env('MICROSTOCK_AI_THINKING_API_KEY'),
            'timeout' => (int) env('MICROSTOCK_AI_THINKING_TIMEOUT', 60),
        ],

        // Longest side (px) of the downscaled preview sent to the AI vision
        // stage. The original file is never modified; the small derivative keeps
        // the gateway from hitting its ~60s cutoff on full-size images.
        'image_max_px' => (int) env('MICROSTOCK_AI_IMAGE_MAX_PX', 1024),

        // How many images are packed into one batch AI request when analyzing a
        // whole collection. Larger chunks = fewer requests but bigger payloads.
        'batch_chunk' => (int) env('MICROSTOCK_AI_BATCH_CHUNK', 10),
    ],

    // Microstock collections accept only these raster image mime types; the AI
    // vision stage cannot read anything else.
    'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],

    'csv' => [
        // Columns emitted by the metadata CSV export, in order.
        'columns' => ['filename', 'title', 'keywords'],
        'keywords_separator' => ',',
    ],

    // Filesystem disk that stores generated per-collection CSV exports.
    'csv_disk' => env('MICROSTOCK_CSV_DISK', 'local'),
];
