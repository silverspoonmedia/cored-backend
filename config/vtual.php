<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform credential test / runtime tokens
    |--------------------------------------------------------------------------
    |
    | Manual Test gate probes live platform APIs with admin secrets. Twitch app
    | access tokens land in runtime_payload (envelope), not secret_payload.
    |
    */

    'credential_test' => [
        // Public Google Developers channel — stable id for YouTube Data API smoke.
        'youtube_probe_channel_id' => env('VTUAL_YT_PROBE_CHANNEL_ID', 'UC_x5XG1OV2P6uZZ5FSM9Ttw'),

        // Re-mint Twitch app token this many seconds before expires_at.
        'twitch_token_skew_seconds' => (int) env('VTUAL_TWITCH_TOKEN_SKEW_SECONDS', 300),

        'http_timeout' => (int) env('VTUAL_CREDENTIAL_HTTP_TIMEOUT', 20),
    ],

];
