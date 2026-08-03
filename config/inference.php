<?php

/**
 * Core inference pricing — provider-first model rates.
 * Mutable rates live in DB (InferenceCatalogSeeder); unregistered slugs use unknown_model_rates.
 */
return [
    'default_provider_slug' => 'openwebui-9router',

    'default_rates' => [
        'input_per_million_usd' => 5.0,
        'output_per_million_usd' => 20.0,
    ],

    'unknown_model_rates' => [
        'input_per_million_usd' => 15.0,
        'output_per_million_usd' => 60.0,
    ],
];
