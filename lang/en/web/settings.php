<?php

return [
    'title' => 'Settings',
    'subtitle' => 'Operational configuration managed through the admin UI.',
    'read_only' => 'You have view-only access to these settings.',
    'source' => 'Current source: :source',
    'groups' => [
        'platform' => 'Platform',
        'file_hosting' => 'File Hosting',
        'microstock' => 'Microstock',
        'vtual' => 'vTual',
        'notification_audiences' => 'Notification audiences',
        'currencies' => 'Currencies',
    ],
    'fields' => [
        'platform.jwt.ttl' => 'JWT access TTL (minutes)',
        'platform.jwt.refresh_ttl' => 'JWT refresh window (minutes)',
        'platform.jwt.remember_ttl' => 'JWT remember-me TTL (minutes)',
        'platform.jwt.refresh_skew' => 'JWT silent refresh skew (minutes)',
        'platform.jwt.blacklist_grace_period' => 'JWT blacklist grace (seconds)',
        'platform.hcaptcha.enabled' => 'hCaptcha enabled',
        'platform.notifications.delivery' => 'Notification delivery mode',
        'platform.notifications.polling_interval' => 'Notification poll interval (seconds)',
        'platform.errorlog.enabled' => 'Persistent error log enabled',
        'platform.errorlog.clean_after_days' => 'Error log retention (days)',
        'platform.service.grace_days' => 'Service grace days',
        'platform.service.settlement_currency' => 'Settlement currency',
        'platform.payment.channel_fee_bearer' => 'Channel fee bearer',
        'platform.payment.reporting_timezone' => 'Payment reporting timezone',
    ],
    'hints' => [],
];
