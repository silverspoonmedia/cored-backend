<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Internal web audience (core platform staff)
    |--------------------------------------------------------------------------
    |
    | Root / Admin / Moderator — gates vTual admin Blade routes and menu branch.
    |
    */

    'internal_web_roles' => ['Root', 'Admin', 'Moderator'],

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
        // Tier B — Settings → vTual (vtual.credential_test.youtube_probe_channel_id)
        'youtube_probe_channel_id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw',

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
            // YouTube playlistItems.list max per request
            'playlist_items' => 50,
            // YouTube search.list max per request (live discovery fallback)
            'search_channel_videos' => 5,
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
            // Music/non-music layer reads topicDetails.topicCategories — the only
            // reliable signal for vTuber covers filed under Gaming/People & Blogs.
            // Removing it silently degrades classification to categoryId-only.
            'topicDetails',
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

        'default_playlist_item_parts' => [
            'snippet',
            'contentDetails',
            'status',
        ],

        'allowed_playlist_item_parts' => [
            'id',
            'snippet',
            'contentDetails',
            'status',
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
        // Tier B — Settings → vTual (vtual.claim.token_*)
        'token_prefix' => 'vtual-claim-',
        'token_bytes' => 6,
        'ttl_hours' => 24,
        'verify_cooldown_seconds' => 60,
        'max_attempts' => 3,
        'block_hours_after_max_attempts' => 24,
        // Soft cap on concurrent pending claims per user (audit rows kept).
        'max_pending_per_user' => 5,
        // Local/dev default ON for QA without editing live bios.
        'accept_anyway_enabled' => true,
        'discord_verify_enabled' => false,
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
        // Tier B — Settings → vTual (vtual.channel_claim.token_*)
        'token_prefix' => 'vtual-ch-',
        'token_bytes' => 6,
        'ttl_hours' => 24,
        'verify_cooldown_seconds' => 60,
        'max_attempts' => 20,
        'accept_anyway_enabled' => true,
        'discord_verify_enabled' => false,
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

    /*
    |--------------------------------------------------------------------------
    | Creator claim verify pool (queued bio verification)
    |--------------------------------------------------------------------------
    |
    | Profile claim verify is enqueued then flushed when pending >= threshold
    | OR oldest pending age >= max_wait (strict pool — no immediate flush below
    | threshold). Batch channel fetches up to http.batch.channels per platform.
    |
    */
    'claim_verify_pool' => [
        'threshold' => 50,
        'max_wait_minutes' => 15,
        'flush_lock_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content fetch pool (video id hydrate after feed / WebSub discovery)
    |--------------------------------------------------------------------------
    */
    'content_pool' => [
        'threshold' => 50,
        'max_wait_minutes' => 15,
        'flush_lock_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fetch pool retention (processed rows)
    |--------------------------------------------------------------------------
    |
    | done/failed rows in vtual_channel_fetch_pool + vtual_content_fetch_pool
    | are audit only; prune after this many days via vtual:prune-fetch-pools.
    |
    */
    'pool_retention' => [
        'days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feed poll + live CCV + WebSub lease
    |--------------------------------------------------------------------------
    */
    'feed_poll' => [
        'interval_minutes' => 15,
    ],

    'live_poll' => [
        'interval_minutes' => 2,
    ],

    'websub' => [
        'lease_seconds' => 864000,
        'renew_before_hours' => 24,
        'max_body_bytes' => 1_048_576,
    ],

    'eventsub' => [
        'renew_before_hours' => 24,
        'max_body_bytes' => 1_048_576,
        'dedupe_ttl_hours' => 6,
        'repair_limit' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel archive / restore rate limits
    |--------------------------------------------------------------------------
    */
    'channel_lifecycle' => [
        'cooldown_seconds' => 30,
        'max_per_minute' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content classification (cover song detection)
    |--------------------------------------------------------------------------
    |
    | Heuristic multilingual keyword classifier + optional iTunes Search
    | verification for weak "cover" signals. Runs inline during video hydrate;
    | never throws — classification failure leaves content_category null.
    |
    */
    'content_classification' => [
        // YouTube categoryId "10" = Music — prefilter for weak-signal lookup.
        // Also signal #2 of the music/non-music layer, but NOT sufficient alone:
        // vTubers file covers under 20 (Gaming) / 22 (People & Blogs).
        'music_category_id' => '10',
        // YouTube topicDetails topicCategories entry that means "this is music".
        // The strongest signal available — present on covers regardless of the
        // uploader's categoryId choice, and absent on gameplay/ASMR/talk samples.
        'music_topic' => 'https://en.wikipedia.org/wiki/Music',
        // Cover duration window in seconds (typical song recording).
        'cover_duration' => ['min' => 90, 'max' => 420],
        // No positive signal + at least this long = non-music (streams, let's
        // plays, talk shows). Shorter/unknown = unknown → async AI audit decides.
        'long_form_min_seconds' => 600,
        // Markers in a description that credit an original release ("本家様：…",
        // "原曲：…"). Only counts when the description also links a video.
        'original_credit_markers' => ['本家', '原曲', 'original'],
        // Sub-category (cover) provenance markers. Deliberately narrower than
        // `original_credit_markers`: an original MV's own description says
        // "Original MV / https://youtu.be/…", so the English word "original"
        // would label originals as covers.
        'cover_provenance_markers' => ['本家', '原曲'],
        // Composer-side credit: the uploader wrote the song and handed it to a
        // singer ("兎迷夢々さんに提供した楽曲です"). Phrase-level on purpose — the
        // bare 提供 also appears in "音源提供" (an instrumental licensed to a
        // cover singer), which must NOT read as original.
        'original_provide_markers' => ['提供した楽曲', '提供曲'],
        // Title fragments that credit cover ART (illustration/jacket), not a
        // cover PERFORMANCE. Stripped from the title before keyword matching:
        // "Original Song" + "Cover Art: X" must not read as a cover.
        'cover_decoy_phrases' => [
            'cover art', 'cover illustration', 'cover design', 'cover photo',
            'cover image', 'artwork', 'illustration', 'illust', 'jacket',
        ],
        // Parenthesised cover labels are strong signals — they wrap the label
        // itself, unlike the bare word that also appears in art credits.
        'cover_paren_keywords' => [
            '(cover)', '（cover）', '( cover )', '(カバー)', '（カバー）',
        ],
        // Uploader-authored tag labels (snippet.tags). Read only on the hydrate
        // path — stored rows do not persist tags.
        'tag_cover' => ['歌ってみた', '歌みた', 'うたってみた', 'cover', 'カバー'],
        'tag_original' => ['オリジナル曲', 'オリジナル楽曲', 'オリジナルmv', 'original song', 'original mv'],
        'tag_singing_stream' => ['歌枠', 'カラオケ枠', 'singing stream', 'karaoke stream'],
        'itunes' => [
            'enabled' => true,
            'base_url' => 'https://itunes.apple.com',
            'timeout' => 5,
            // Wider than the old 5 so the earliest-release tie-break has
            // candidates to choose from when a cover chart-ranks higher.
            'limit' => 25,
            'cache_ttl_hours' => 168,
            // Compare the catalog artist against the uploader's channel title:
            // equal means the uploader released the song (original), different
            // means they performed someone else's song (cover). This is what
            // resolves keyword-free uploads like 【GUMI】KING【Kanaria】 without AI.
            // Ordering picks the earliest release among the title-matching
            // candidates: a cover single chart-ranks higher and ships later, so
            // the original release wins the tie ("KING" → Kanaria 2020, not the
            // 2021 麻婆豆腐 single).
            'artist_disambiguation' => true,
        ],
        // AI audit pass for rows the program classifier could not resolve
        // (music/non-music unknown, or music with no sub-category).
        // Endpoint + API key live in the core Ai domain (`ai_connections`, Tier C).
        // Tier B — connection binding / enabled / model / max_tokens / timeout /
        // min_confidence / cache_ttl via Settings → vTual
        // (vtual.content_classification.ai.*). Empty `connection` = the default
        // AI connection. `max_tokens` must cover reasoning tokens for
        // reasoning-style models: too low and the model spends the whole budget
        // on reasoning, returning an empty `content` (`finish_reason: length`).
        // Measured: 600 returned empty content on 4 of 5 real videos, 4000 worked.
        'ai' => [
            'connection' => '',
            'enabled' => false,
            'model' => '',
            'timeout' => 30,
            'max_tokens' => 4000,
            'min_confidence' => 0.7,
            'cache_ttl_hours' => 168,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public landing page (/vtual SPA home)
    |--------------------------------------------------------------------------
    |
    | Tier B — Settings → vTual (vtual.landing.event.*). Featured event drives
    | the countdown banner + events section. Empty starts_at hides the banner.
    |
    */
    'landing' => [
        'event' => [
            'title' => '',
            // ISO 8601 datetime, e.g. '2026-09-01T20:00:00+09:00'. Empty = hidden.
            'starts_at' => '',
            'body' => '',
        ],
    ],

];
