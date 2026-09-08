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
        'archivd.download_vault.enabled' => true,
        'microstock.ai.http_retries' => 1,
        'vtual.claim.accept_anyway_enabled' => true,
        'vtual.claim.discord_verify_enabled' => true,
        'vtual.channel_claim.accept_anyway_enabled' => true,
        // Local Open WebUI model aliases + shorter stage timeouts (ops, not secrets).
        'microstock.ai.vision.model' => 'internal.sspn_pro_vision',
        'microstock.ai.vision.free_model' => 'internal.sspn_free_vision',
        'microstock.ai.vision.timeout' => 300,
        'microstock.ai.thinking.model' => 'internal.sspn_pro_thinking',
        'microstock.ai.thinking.free_model' => 'internal.sspn_free_thinking',
        'microstock.ai.thinking.timeout' => 300,
    ],

];
