<?php

/**
 * Local-only Tier B overrides (APP_ENV=local).
 *
 * Applied by LocalSettingsSeeder after SettingsSeeder via upsertMany.
 * Do not put secrets (Tier A) or staging/production ergonomics here —
 * change config/*.php literals for prod-safe defaults instead.
 *
 * @see database/seeders/LocalSettingsSeeder.php
 * @see docs/platform/configuration-policy.md
 */
return [

    'overrides' => [
        'platform.notifications.delivery' => 'realtime',
        // Local Open WebUI model aliases + shorter stage timeouts (ops, not secrets).
        // Aliases must exist in the endpoint's `/api/models` catalogue — check with
        // `php scripts/ai/list_models.php` after changing them here.
    ],

];
