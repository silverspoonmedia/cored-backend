<?php

/**
 * Billing's slice of `config/services.php`.
 *
 * A consumer that installs `silverspoonmedia/billing` alone still needs the FX
 * provider the checkout converts through, so the defaults live here and
 * `BillingServiceProvider` merges them. `mergeConfigFrom` is shallow, so a host
 * that keeps its own `frankfurter` block wins key-for-key.
 */
return [

    'frankfurter' => [
        'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.app'),
        // Tier B — Settings → Platform (platform.frankfurter.timeout / cache_ttl)
        'timeout' => 10,
        'cache_ttl' => 3600,
    ],

];
