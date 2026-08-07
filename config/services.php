<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frankfurter FX (ECB rates, no API key)
    |--------------------------------------------------------------------------
    | Used by Service domain checkout to convert display currency → IDR.
    | @see https://www.frankfurter.app/docs/
    */
    'frankfurter' => [
        'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.app'),
        // Tier B — Settings → Platform (platform.frankfurter.timeout / cache_ttl)
        'timeout' => 10,
        'cache_ttl' => 3600,
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI') ?: rtrim((string) config('app.url'), '/').'/apps/auth/discord/callback',
        // Tier B — Settings → Identity (identity.discord.connections_snapshot_max_age_minutes)
        'connections_snapshot_max_age_minutes' => 60,
    ],

];
