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
        // Local vTual AI audit: the classifier needs `enabled` plus a model; the
        // alias must exist in the endpoint catalogue (see below). The pass now
        // decides both layers (music/non-music + sub-category), so the answer is
        // longer than before. `max_tokens` / `timeout` are raised because the
        // local alias is a reasoning model that spends the budget on reasoning
        // tokens before it emits any JSON (the prod-safe literals are much
        // tighter). Measured: 600 returned empty content on 4 of 5 videos.
        'vtual.content_classification.ai.enabled' => true,
        'vtual.content_classification.ai.model' => 'internal.sspn_misc',
        'vtual.content_classification.ai.max_tokens' => 4_000,
        'vtual.content_classification.ai.timeout' => 120,
        // Local Open WebUI model aliases + shorter stage timeouts (ops, not secrets).
        // Aliases must exist in the endpoint's `/api/models` catalogue — check with
        // `php scripts/ai/list_models.php` after changing them here.
        'microstock.ai.vision.model' => 'internal.sspn_vision',
        'microstock.ai.vision.free_model' => '',
        'microstock.ai.vision.timeout' => 300,
        'microstock.ai.thinking.model' => 'internal.sspn_thinking',
        'microstock.ai.thinking.free_model' => '',
        'microstock.ai.thinking.timeout' => 300,
    ],

];
