<?php

/**
 * Core inference pricing — provider-first model rates.
 * Mutable rates live in DB (InferenceCatalogSeeder); unregistered slugs use unknown_model_rates.
 */
return [
    'default_provider_slug' => env('INFERENCE_DEFAULT_PROVIDER_SLUG', 'openwebui-9router'),

    'default_rates' => [
        'input_per_million_usd' => (float) env('INFERENCE_DEFAULT_INPUT_PER_MILLION_USD', 5.0),
        'output_per_million_usd' => (float) env('INFERENCE_DEFAULT_OUTPUT_PER_MILLION_USD', 20.0),
    ],

    'unknown_model_rates' => [
        'input_per_million_usd' => (float) env('INFERENCE_UNKNOWN_MODEL_INPUT_PER_MILLION_USD', 15.0),
        'output_per_million_usd' => (float) env('INFERENCE_UNKNOWN_MODEL_OUTPUT_PER_MILLION_USD', 60.0),
    ],
];
