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

    /*
    |--------------------------------------------------------------------------
    | Manual platform HTTP clients (Data API / Helix)
    |--------------------------------------------------------------------------
    |
    | Batch caps use the lowest common limit across platforms so multi-id
    | helpers stay interchangeable without silent multi-request surprises.
    |
    */

    'http' => [
        'timeout' => (int) env('VTUAL_HTTP_TIMEOUT', env('VTUAL_CREDENTIAL_HTTP_TIMEOUT', 20)),

        'batch' => [
            // min(YouTube videos.list 50, Twitch Get Videos by id 100)
            'videos' => 50,
            // min(YouTube channels.list 50, Twitch Get Users 100)
            'channels' => 50,
            // Twitch Get Streams user_id max (platform-specific)
            'streams_user_ids' => 100,
        ],
    ],

    'youtube' => [
        'base_url' => 'https://www.googleapis.com/youtube/v3',

        'default_video_parts' => [
            'snippet',
            'contentDetails',
            'status',
            'statistics',
            'liveStreamingDetails',
        ],

        'default_channel_parts' => [
            'snippet',
            'contentDetails',
            'statistics',
        ],

        'allowed_video_parts' => [
            'id',
            'snippet',
            'contentDetails',
            'status',
            'statistics',
            'liveStreamingDetails',
            'player',
            'topicDetails',
            'recordingDetails',
            'localizations',
        ],

        'allowed_channel_parts' => [
            'id',
            'snippet',
            'contentDetails',
            'statistics',
            'status',
            'brandingSettings',
            'topicDetails',
            'localizations',
        ],
    ],

    'twitch' => [
        'helix_base_url' => 'https://api.twitch.tv/helix',
    ],

];
