<?php

return [
    'title' => 'Settings',
    'subtitle' => 'Operational configuration managed through the admin UI.',
    'read_only' => 'You have view-only access to these settings.',
    'source' => 'Current source: :source',
    'groups' => [
        'platform' => 'Platform',
        'notification_audiences' => 'Notification audiences',
        'currencies' => 'Currencies',
    ],
    'fields' => [
        // Platform — JWT
        'platform.jwt.ttl' => 'JWT access TTL (minutes)',
        'platform.jwt.refresh_ttl' => 'JWT refresh window (minutes)',
        'platform.jwt.remember_ttl' => 'JWT remember-me TTL (minutes)',
        'platform.jwt.refresh_skew' => 'JWT silent refresh skew (minutes)',
        'platform.jwt.blacklist_grace_period' => 'JWT blacklist grace (seconds)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => 'Default role for new users',
        'platform.auth.verification_expire' => 'Email verification link expiry (minutes)',
        'platform.two_factor.window' => '2FA TOTP validation window',
        'platform.two_factor.issuer' => '2FA issuer name (authenticator app)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha enabled',
        // Platform — notifications
        'platform.notifications.delivery' => 'Notification delivery mode',
        'platform.notifications.polling_interval' => 'Notification poll interval (seconds)',
        // Platform — error log
        'platform.errorlog.enabled' => 'Persistent error log enabled',
        'platform.errorlog.clean_after_days' => 'Error log retention (days)',
        'platform.errorlog.max_message_length' => 'Error log max message length',
        'platform.errorlog.max_trace_length' => 'Error log max trace length',
        'platform.errorlog.max_context_json_length' => 'Error log max context JSON length',
        // Platform — activity log
        'platform.activitylog.enabled' => 'Activity log enabled',
        'platform.activitylog.buffer_enabled' => 'Activity log buffer enabled',
        // Platform — localization
        'platform.localization.locale_cookie' => 'Locale cookie name',
        'identity.discord.connections_snapshot_max_age_minutes' => 'Discord connections snapshot max age (minutes)',
        'platform.localization.locale_cookie_minutes' => 'Locale cookie lifetime (minutes)',
        // Platform — payment
        'platform.payment.channel_fee_bearer' => 'Channel fee bearer',
        'platform.payment.reporting_timezone' => 'Payment reporting timezone',
        'platform.payment.prefer_channel_checkout' => 'Prefer channel-native checkout',
        'platform.payment.default_buyer_phone' => 'Default buyer phone (iPaymu fallback)',
        // Platform — mail identity
        'platform.mail.from_address' => 'Mail from address',
        'platform.mail.from_name' => 'Mail from name',
        // Platform — service
        'platform.service.grace_days' => 'Service grace days',
        'platform.service.settlement_currency' => 'Settlement currency',
        'platform.service.purchase_max_quantity' => 'Max service purchase quantity',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'Default inference provider slug',
        'platform.inference.default_input_per_million_usd' => 'Default input rate (USD per 1M tokens)',
        'platform.inference.default_output_per_million_usd' => 'Default output rate (USD per 1M tokens)',
        'platform.inference.unknown_input_per_million_usd' => 'Unknown model input rate (USD per 1M tokens)',
        'platform.inference.unknown_output_per_million_usd' => 'Unknown model output rate (USD per 1M tokens)',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'Frankfurter FX freshness window (seconds)',
        'platform.frankfurter.timeout' => 'Frankfurter FX HTTP timeout (seconds)',
    ],
    'hints' => [
        'platform.mail.from_address' => 'Global From address for outbound mail (transport credentials stay in .env).',
        'platform.payment.default_buyer_phone' => 'Fallback phone when the buyer profile has no phone (iPaymu requirement).',
        'platform.frankfurter.timeout' => 'HTTP timeout for Frankfurter FX lookups.',
        'platform.jwt.refresh_skew' => 'Refresh the access token this many minutes before it expires.',
        'platform.notifications.delivery' => 'Realtime uses WebSockets and requires configured PUSHER_* / broadcast credentials; otherwise the bell falls back to HTTP polling.',
        'platform.payment.channel_fee_bearer' => 'Who absorbs payment channel fees: merchant or customer.',
        'platform.inference.default_provider_slug' => 'Fallback provider when none is specified on a request.',
        'platform.inference.default_input_per_million_usd' => 'Provisional billing rate for registered models without catalog pricing.',
        'platform.inference.unknown_input_per_million_usd' => 'Provisional billing rate for unregistered models.',
        'identity.discord.connections_snapshot_max_age_minutes' => 'How long stored Discord connection snapshots are trusted before a verify miss triggers a live Discord API refresh.',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'HTTP polling',
                    'realtime' => 'WebSocket (realtime)',
                ],
            ],
        ],
    ],
];
