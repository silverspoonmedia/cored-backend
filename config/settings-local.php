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
        'microstock.daily_free.enabled' => true,
        'microstock.daily_free.limit_per_user' => 1_000_000,
        'platform.notifications.delivery' => 'realtime',
        'file-hosting.download_vault.enabled' => true,
        'microstock.ai.http_retries' => 1,
        'vtual.claim.accept_anyway_enabled' => true,
        'vtual.channel_claim.accept_anyway_enabled' => true,
    ],

];
