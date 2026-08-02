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
        'twitch_token_skew_seconds' => 300,

        'http_timeout' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credential failover (soft demote)
    |--------------------------------------------------------------------------
    |
    | Default OFF. On 401/403 only: increment consecutive_auth_failures; at
    | threshold set failover_skip_until (status stays active). Gateway retries
    | once with next pickActive. Never auto-promotes pending.
    |
    */
    'credential' => [
        'failover' => [
            'enabled' => false,
            'auth_fail_threshold' => 2,
            'skip_minutes' => 30,
            // 1 = one alternate credential after the first failure path allows switch
            'max_retries_per_call' => 1,
        ],
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
        'timeout' => 20,

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
            'brandingSettings',
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

    /*
    |--------------------------------------------------------------------------
    | Creator claim (bio token / Accept Anyway)
    |--------------------------------------------------------------------------
    |
    | Phase 1: claim orphan (no owner member) profiles by putting a token in
    | the platform channel About/bio, or staff Accept Anyway when enabled.
    |
    */
    'claim' => [
        'token_prefix' => env('VTUAL_CLAIM_TOKEN_PREFIX', 'vtual-claim-'),
        'token_bytes' => (int) env('VTUAL_CLAIM_TOKEN_BYTES', 6),
        'ttl_hours' => 24,
        'verify_cooldown_seconds' => 60,
        'max_attempts' => 20,
        // Soft cap on concurrent pending claims per user (audit rows kept).
        'max_pending_per_user' => 5,
        // Local/dev default ON for QA without editing live bios.
        'accept_anyway_enabled' => true,
        'vtuber_role' => 'Vtual vTuber',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel claim (attach channel via bio token / Accept Anyway)
    |--------------------------------------------------------------------------
    |
    | Add channel on a vTuber profile does NOT auto-bind. Resolve → pending
    | claim + token in platform About/bio → verify (or staff Accept Anyway)
    | → insert active vtual_channels row.
    |
    */
    'channel_claim' => [
        'token_prefix' => env('VTUAL_CHANNEL_CLAIM_TOKEN_PREFIX', 'vtual-ch-'),
        'token_bytes' => (int) env('VTUAL_CHANNEL_CLAIM_TOKEN_BYTES', 6),
        'ttl_hours' => 24,
        'verify_cooldown_seconds' => 60,
        'max_attempts' => 20,
        'accept_anyway_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel fetch pool (batch by external_id)
    |--------------------------------------------------------------------------
    |
    | Non-interactive channel snapshot fetches (refresh, scheduled resync) are
    | enqueued then flushed when pending count hits threshold OR oldest item
    | exceeds max_wait. Single locked flusher prevents double HTTP for the same
    | claimed set. Interactive claim/seed/verify stay sync single-id.
    |
    */
    'channel_pool' => [
        'threshold' => 50,
        'max_wait_minutes' => 15,
        'schedule_every_minutes' => 1,
        'resync_stale_hours' => 24,
        'resync_enqueue_limit' => 200,
        'flush_lock_seconds' => 30,
    ],

];
