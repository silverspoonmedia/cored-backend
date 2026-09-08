<?php

namespace App\Support\Settings;

/**
 * Canonical registry of UI-manageable operational settings.
 *
 * Each key maps to a config() path used as env-backed fallback when no DB row exists.
 */
final class SettingRegistry
{
    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            // Platform — JWT
            'platform.jwt.ttl' => self::def('platform', SettingType::Int, 'jwt.ttl'),
            'platform.jwt.refresh_ttl' => self::def('platform', SettingType::Int, 'jwt.refresh_ttl'),
            'platform.jwt.remember_ttl' => self::def('platform', SettingType::Int, 'jwt.remember_ttl'),
            'platform.jwt.refresh_skew' => self::def('platform', SettingType::Int, 'jwt.refresh_skew'),
            'platform.jwt.blacklist_grace_period' => self::def('platform', SettingType::Int, 'jwt.blacklist_grace_period'),

            // Platform — auth / 2FA
            'platform.auth.default_role' => self::def('platform', SettingType::String, 'auth.default_role'),
            'platform.auth.verification_expire' => self::def('platform', SettingType::Int, 'auth.verification.expire'),
            'platform.two_factor.window' => self::def('platform', SettingType::Int, 'auth.two_factor.window'),
            'platform.two_factor.issuer' => self::def('platform', SettingType::String, 'auth.two_factor.issuer'),

            // Platform — hCaptcha
            'platform.hcaptcha.enabled' => self::def('platform', SettingType::Bool, 'hcaptcha.enabled'),

            // Platform — notifications
            'platform.notifications.delivery' => self::def('platform', SettingType::String, 'notifications.delivery', ['polling', 'realtime']),
            'platform.notifications.polling_interval' => self::def('platform', SettingType::Int, 'notifications.polling_interval'),

            // Platform — error log
            'platform.errorlog.enabled' => self::def('platform', SettingType::Bool, 'errorlog.enabled'),
            'platform.errorlog.clean_after_days' => self::def('platform', SettingType::Int, 'errorlog.clean_after_days'),
            'platform.errorlog.max_message_length' => self::def('platform', SettingType::Int, 'errorlog.max_message_length'),
            'platform.errorlog.max_trace_length' => self::def('platform', SettingType::Int, 'errorlog.max_trace_length'),
            'platform.errorlog.max_context_json_length' => self::def('platform', SettingType::Int, 'errorlog.max_context_json_length'),

            // Platform — activity log
            'platform.activitylog.enabled' => self::def('platform', SettingType::Bool, 'activitylog.enabled'),
            'platform.activitylog.buffer_enabled' => self::def('platform', SettingType::Bool, 'activitylog.buffer.enabled'),

            // Platform — localization
            'platform.localization.locale_cookie' => self::def('platform', SettingType::String, 'localization.cookie_name'),

            // Identity — Discord OAuth
            'identity.discord.connections_snapshot_max_age_minutes' => self::def(
                'identity',
                SettingType::Int,
                'services.discord.connections_snapshot_max_age_minutes',
            ),
            'platform.localization.locale_cookie_minutes' => self::def('platform', SettingType::Int, 'localization.cookie_minutes'),

            // Platform — mail identity (transport secrets stay env)
            'platform.mail.from_address' => self::def('platform', SettingType::String, 'mail.from.address'),
            'platform.mail.from_name' => self::def('platform', SettingType::String, 'mail.from.name'),

            // Platform — payment checkout
            'platform.payment.channel_fee_bearer' => self::def('platform', SettingType::String, 'payment.channel_fee_bearer'),
            'platform.payment.reporting_timezone' => self::def('platform', SettingType::String, 'payment.reporting_timezone'),
            'platform.payment.prefer_channel_checkout' => self::def('platform', SettingType::Bool, 'payment.checkout.prefer_channel_checkout'),
            'platform.payment.default_buyer_phone' => self::def('platform', SettingType::String, 'payment.default_buyer_phone'),

            // Platform — service
            'platform.service.grace_days' => self::def('platform', SettingType::Int, 'service.grace_days'),
            'platform.service.settlement_currency' => self::def('platform', SettingType::String, 'service.settlement_currency'),
            'platform.service.purchase_max_quantity' => self::def('platform', SettingType::Int, 'service.purchase.max_quantity'),

            // Platform — inference fallbacks
            'platform.inference.default_provider_slug' => self::def('platform', SettingType::String, 'inference.default_provider_slug'),
            'platform.inference.default_input_per_million_usd' => self::def('platform', SettingType::Float, 'inference.default_rates.input_per_million_usd'),
            'platform.inference.default_output_per_million_usd' => self::def('platform', SettingType::Float, 'inference.default_rates.output_per_million_usd'),
            'platform.inference.unknown_input_per_million_usd' => self::def('platform', SettingType::Float, 'inference.unknown_model_rates.input_per_million_usd'),
            'platform.inference.unknown_output_per_million_usd' => self::def('platform', SettingType::Float, 'inference.unknown_model_rates.output_per_million_usd'),

            // Platform — Frankfurter FX
            'platform.frankfurter.cache_ttl' => self::def('platform', SettingType::Int, 'services.frankfurter.cache_ttl'),
            'platform.frankfurter.timeout' => self::def('platform', SettingType::Int, 'services.frankfurter.timeout'),

            // Archivd
            'archivd.max_file_size_bytes' => self::def('archivd', SettingType::Int, 'archivd.max_file_size_bytes'),
            'archivd.max_files_per_batch' => self::def('archivd', SettingType::Int, 'archivd.max_files_per_batch'),
            'archivd.max_concurrent_uploads' => self::def('archivd', SettingType::Int, 'archivd.max_concurrent_uploads'),
            'archivd.max_concurrent_parts' => self::def('archivd', SettingType::Int, 'archivd.max_concurrent_parts'),
            'archivd.chunk_size_bytes' => self::def('archivd', SettingType::Int, 'archivd.chunk_size_bytes'),
            'archivd.multipart_threshold_bytes' => self::def('archivd', SettingType::Int, 'archivd.multipart_threshold_bytes'),
            'archivd.max_presigned_parts_per_response' => self::def('archivd', SettingType::Int, 'archivd.max_presigned_parts_per_response'),
            'archivd.presign_ttl_minutes' => self::def('archivd', SettingType::Int, 'archivd.presign_ttl_minutes'),
            'archivd.session_ttl_hours' => self::def('archivd', SettingType::Int, 'archivd.session_ttl_hours'),
            'archivd.stale_upload_grace_hours' => self::def('archivd', SettingType::Int, 'archivd.stale_upload_grace_hours'),
            'archivd.orphan_inactivity_minutes' => self::def('archivd', SettingType::Int, 'archivd.orphan_inactivity_minutes'),
            'archivd.default_quota_bytes' => self::def('archivd', SettingType::Int, 'archivd.default_quota_bytes'),
            'archivd.base_owned_workspace_slots' => self::def('archivd', SettingType::Int, 'archivd.base_owned_workspace_slots'),
            'archivd.disabled_regions' => self::def('archivd', SettingType::Array, 'archivd.disabled_regions'),
            'archivd.signed_download_ttl_minutes' => self::def('archivd', SettingType::Int, 'archivd.signed_download_ttl_minutes'),
            'archivd.signed_download_cache_margin_seconds' => self::def('archivd', SettingType::Int, 'archivd.signed_download_cache_margin_seconds'),
            'archivd.public_download_signature_ttl_minutes' => self::def('archivd', SettingType::Int, 'archivd.public_download_signature_ttl_minutes'),
            'archivd.allowed_mime_prefixes' => self::def('archivd', SettingType::Array, 'archivd.allowed_mime_prefixes'),
            'archivd.download_vault.enabled' => self::def('archivd', SettingType::Bool, 'archivd.download_vault.enabled'),

            // Microstock
            'microstock.daily_free.enabled' => self::def('microstock', SettingType::Bool, 'microstock.daily_free.enabled'),
            'microstock.daily_free.limit_per_user' => self::def('microstock', SettingType::Int, 'microstock.daily_free.limit_per_user'),
            'microstock.daily_free.timezone' => self::def('microstock', SettingType::String, 'microstock.daily_free.timezone'),
            'microstock.ai_billing.markup_multiplier' => self::def('microstock', SettingType::Float, 'microstock.ai_billing.markup_multiplier'),
            'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => self::def('microstock', SettingType::Int, 'microstock.ai_billing.estimated_billable_micro_usd_per_asset'),
            'microstock.ai_billing.estimate_thinking_attempt_factor' => self::def('microstock', SettingType::Float, 'microstock.ai_billing.estimate_thinking_attempt_factor'),
            'microstock.ai.image_max_px' => self::def('microstock', SettingType::Int, 'microstock.ai.image_max_px'),
            'microstock.ai.image_jpeg_quality' => self::def('microstock', SettingType::Int, 'microstock.ai.image_jpeg_quality'),
            'microstock.ai.max_request_image_bytes' => self::def('microstock', SettingType::Int, 'microstock.ai.max_request_image_bytes'),
            'microstock.ai.http_retries' => self::def('microstock', SettingType::Int, 'microstock.ai.http_retries'),
            'microstock.ai.thinking_visual_attempts' => self::def('microstock', SettingType::Int, 'microstock.ai.thinking_visual_attempts'),
            'microstock.ai.batch_chunk' => self::def('microstock', SettingType::Int, 'microstock.ai.batch_chunk'),
            'microstock.ai.batch_min_files' => self::def('microstock', SettingType::Int, 'microstock.ai.batch_min_files'),
            'microstock.ai.phase.normal_after' => self::def('microstock', SettingType::Int, 'microstock.ai.phase.normal_after'),
            'microstock.ai.phase.grace_after' => self::def('microstock', SettingType::Int, 'microstock.ai.phase.grace_after'),
            'microstock.ai.stage.vision_grace_after' => self::def('microstock', SettingType::Int, 'microstock.ai.stage.vision_grace_after'),
            'microstock.ai.stage.thinking_grace_after' => self::def('microstock', SettingType::Int, 'microstock.ai.stage.thinking_grace_after'),
            'microstock.ai.stage.thinking_backlog_after' => self::def('microstock', SettingType::Int, 'microstock.ai.stage.thinking_backlog_after'),
            'microstock.ai.vision_image.delivery' => self::def('microstock', SettingType::String, 'microstock.ai.vision_image.delivery'),
            'microstock.ai.vision_image.variant_kind' => self::def('microstock', SettingType::String, 'microstock.ai.vision_image.variant_kind'),
            'microstock.ai.vision_image.signed_url_ttl_minutes' => self::def('microstock', SettingType::Int, 'microstock.ai.vision_image.signed_url_ttl_minutes'),
            'microstock.ai.inference_provider_slug' => self::def('microstock', SettingType::String, 'microstock.ai.inference_provider_slug'),
            'microstock.ai.vision.model' => self::def('microstock', SettingType::String, 'microstock.ai.vision.model'),
            'microstock.ai.vision.free_model' => self::def('microstock', SettingType::String, 'microstock.ai.vision.free_model'),
            'microstock.ai.vision.timeout' => self::def('microstock', SettingType::Int, 'microstock.ai.vision.timeout'),
            'microstock.ai.thinking.model' => self::def('microstock', SettingType::String, 'microstock.ai.thinking.model'),
            'microstock.ai.thinking.free_model' => self::def('microstock', SettingType::String, 'microstock.ai.thinking.free_model'),
            'microstock.ai.thinking.timeout' => self::def('microstock', SettingType::Int, 'microstock.ai.thinking.timeout'),
            'microstock.ai.upstream.window_seconds' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.window_seconds'),
            'microstock.ai.upstream.token_budget' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.token_budget'),
            'microstock.ai.upstream.max_concurrent_vision' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.max_concurrent_vision'),
            'microstock.ai.upstream.max_concurrent_thinking' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.max_concurrent_thinking'),
            'microstock.ai.upstream.collection_vision_concurrency' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.collection_vision_concurrency'),
            'microstock.ai.upstream.collection_thinking_concurrency' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.collection_thinking_concurrency'),
            'microstock.ai.upstream.solo_vision_concurrency' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.solo_vision_concurrency'),
            'microstock.ai.upstream.solo_thinking_concurrency' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.solo_thinking_concurrency'),
            'microstock.ai.upstream.release_after_seconds' => self::def('microstock', SettingType::Int, 'microstock.ai.upstream.release_after_seconds'),
            'microstock.csv_disk' => self::def('microstock', SettingType::String, 'microstock.csv_disk'),

            // Vtual
            'vtual.credential_test.http_timeout' => self::def('vtual', SettingType::Int, 'vtual.credential_test.http_timeout'),
            'vtual.credential_test.twitch_token_skew_seconds' => self::def('vtual', SettingType::Int, 'vtual.credential_test.twitch_token_skew_seconds'),
            'vtual.credential_test.youtube_probe_channel_id' => self::def('vtual', SettingType::String, 'vtual.credential_test.youtube_probe_channel_id'),
            'vtual.credential.failover.enabled' => self::def('vtual', SettingType::Bool, 'vtual.credential.failover.enabled'),
            'vtual.credential.failover.auth_fail_threshold' => self::def('vtual', SettingType::Int, 'vtual.credential.failover.auth_fail_threshold'),
            'vtual.credential.failover.skip_minutes' => self::def('vtual', SettingType::Int, 'vtual.credential.failover.skip_minutes'),
            'vtual.credential.failover.max_retries_per_call' => self::def('vtual', SettingType::Int, 'vtual.credential.failover.max_retries_per_call'),
            'vtual.http.timeout' => self::def('vtual', SettingType::Int, 'vtual.http.timeout'),
            'vtual.claim.ttl_hours' => self::def('vtual', SettingType::Int, 'vtual.claim.ttl_hours'),
            'vtual.claim.verify_cooldown_seconds' => self::def('vtual', SettingType::Int, 'vtual.claim.verify_cooldown_seconds'),
            'vtual.claim.max_attempts' => self::def('vtual', SettingType::Int, 'vtual.claim.max_attempts'),
            'vtual.claim.block_hours_after_max_attempts' => self::def('vtual', SettingType::Int, 'vtual.claim.block_hours_after_max_attempts'),
            'vtual.claim.max_pending_per_user' => self::def('vtual', SettingType::Int, 'vtual.claim.max_pending_per_user'),
            'vtual.claim.accept_anyway_enabled' => self::def('vtual', SettingType::Bool, 'vtual.claim.accept_anyway_enabled'),
            'vtual.claim.discord_verify_enabled' => self::def('vtual', SettingType::Bool, 'vtual.claim.discord_verify_enabled'),
            'vtual.channel_claim.discord_verify_enabled' => self::def('vtual', SettingType::Bool, 'vtual.channel_claim.discord_verify_enabled'),
            'vtual.claim.token_prefix' => self::def('vtual', SettingType::String, 'vtual.claim.token_prefix'),
            'vtual.claim.token_bytes' => self::def('vtual', SettingType::Int, 'vtual.claim.token_bytes'),
            'vtual.channel_claim.ttl_hours' => self::def('vtual', SettingType::Int, 'vtual.channel_claim.ttl_hours'),
            'vtual.channel_claim.verify_cooldown_seconds' => self::def('vtual', SettingType::Int, 'vtual.channel_claim.verify_cooldown_seconds'),
            'vtual.channel_claim.max_attempts' => self::def('vtual', SettingType::Int, 'vtual.channel_claim.max_attempts'),
            'vtual.channel_claim.accept_anyway_enabled' => self::def('vtual', SettingType::Bool, 'vtual.channel_claim.accept_anyway_enabled'),
            'vtual.channel_claim.token_prefix' => self::def('vtual', SettingType::String, 'vtual.channel_claim.token_prefix'),
            'vtual.channel_claim.token_bytes' => self::def('vtual', SettingType::Int, 'vtual.channel_claim.token_bytes'),
            'vtual.channel_pool.threshold' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.threshold'),
            'vtual.channel_pool.max_wait_minutes' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.max_wait_minutes'),
            'vtual.channel_pool.schedule_every_minutes' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.schedule_every_minutes'),
            'vtual.channel_pool.resync_stale_hours' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.resync_stale_hours'),
            'vtual.channel_pool.resync_enqueue_limit' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.resync_enqueue_limit'),
            'vtual.channel_pool.flush_lock_seconds' => self::def('vtual', SettingType::Int, 'vtual.channel_pool.flush_lock_seconds'),
            'vtual.claim_verify_pool.threshold' => self::def('vtual', SettingType::Int, 'vtual.claim_verify_pool.threshold'),
            'vtual.claim_verify_pool.max_wait_minutes' => self::def('vtual', SettingType::Int, 'vtual.claim_verify_pool.max_wait_minutes'),
            'vtual.claim_verify_pool.flush_lock_seconds' => self::def('vtual', SettingType::Int, 'vtual.claim_verify_pool.flush_lock_seconds'),
            'vtual.content_pool.threshold' => self::def('vtual', SettingType::Int, 'vtual.content_pool.threshold'),
            'vtual.content_pool.max_wait_minutes' => self::def('vtual', SettingType::Int, 'vtual.content_pool.max_wait_minutes'),
            'vtual.content_pool.flush_lock_seconds' => self::def('vtual', SettingType::Int, 'vtual.content_pool.flush_lock_seconds'),
            'vtual.feed_poll.interval_minutes' => self::def('vtual', SettingType::Int, 'vtual.feed_poll.interval_minutes'),
            'vtual.live_poll.interval_minutes' => self::def('vtual', SettingType::Int, 'vtual.live_poll.interval_minutes'),
            'vtual.websub.lease_seconds' => self::def('vtual', SettingType::Int, 'vtual.websub.lease_seconds'),
            'vtual.websub.renew_before_hours' => self::def('vtual', SettingType::Int, 'vtual.websub.renew_before_hours'),
            'vtual.websub.max_body_bytes' => self::def('vtual', SettingType::Int, 'vtual.websub.max_body_bytes'),

            // vTual — public landing page (/vtual SPA)
            'vtual.landing.event.title' => self::def('vtual', SettingType::String, 'vtual.landing.event.title'),
            'vtual.landing.event.starts_at' => self::def('vtual', SettingType::String, 'vtual.landing.event.starts_at'),
            'vtual.landing.event.body' => self::def('vtual', SettingType::String, 'vtual.landing.event.body'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_column(self::definitions(), 'group')));
    }

    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>}>
     */
    public static function forGroup(string $group): array
    {
        return array_filter(
            self::definitions(),
            fn (array $def): bool => $def['group'] === $group,
        );
    }

    /**
     * @return array{group: string, type: SettingType, config: string, options?: list<string>}|null
     */
    public static function get(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    /**
     * Config path → registry key (first definition wins on duplicate paths).
     *
     * @return array<string, string>
     */
    public static function configPathIndex(): array
    {
        static $index = null;

        if ($index !== null) {
            return $index;
        }

        $index = [];

        foreach (self::definitions() as $registryKey => $definition) {
            $index[$definition['config']] ??= $registryKey;
        }

        return $index;
    }

    /**
     * @return array{group: string, type: SettingType, config: string, options?: list<string>}
     */
    private static function def(string $group, SettingType $type, string $config, ?array $options = null): array
    {
        $definition = [
            'group' => $group,
            'type' => $type,
            'config' => $config,
        ];

        if ($options !== null && $options !== []) {
            $definition['options'] = array_values($options);
        }

        return $definition;
    }
}
