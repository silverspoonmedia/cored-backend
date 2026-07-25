<?php

use App\Domain\Service\Support\FileHostingCatalog;

return [

    /*
    |--------------------------------------------------------------------------
    | Default storage disk for new uploads when no region key is supplied.
    |--------------------------------------------------------------------------
    */
    'disk' => env('FILE_HOSTING_DISK', 's3FileHostingCe'),

    /*
    |--------------------------------------------------------------------------
    | Upload regions — region key → Laravel disk name.
    | Only regions with a non-empty disk appear in the upload dropdown.
    | Add a region: set FILE_HOSTING_REGION_XX_DISK + define the disk in
    | config/filesystems.php (s3FileHostingCe / Us / Eu or a custom disk).
    |--------------------------------------------------------------------------
    */
    'regions' => array_filter([
        'ce' => env('FILE_HOSTING_REGION_CE_DISK', 's3FileHostingCe'),
        'us' => env('FILE_HOSTING_REGION_US_DISK'),
        'eu' => env('FILE_HOSTING_REGION_EU_DISK'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Legacy disk name → canonical disk name.
    | Used by RegionResolver so HostedFile rows stored as s3Custom still map
    | to region "ce" and collapse into one historical storage target.
    |--------------------------------------------------------------------------
    */
    'disk_aliases' => [
        's3Custom' => 's3FileHostingCe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regions disabled for new uploads (still serve downloads).
    | Comma-separated region keys. Files already stored on a disabled
    | region's disk remain downloadable — only new uploads are blocked.
    |--------------------------------------------------------------------------
    */
    'disabled_regions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FILE_HOSTING_DISABLED_REGIONS', '')),
    ))),

    'directory' => env('FILE_HOSTING_DIRECTORY', 'file-hosting'),

    /*
    |--------------------------------------------------------------------------
    | Browser part size (recommended 10 MB for CF Worker Free body limits).
    | multipart_threshold_bytes: size < threshold → single PUT; else multipart.
    |--------------------------------------------------------------------------
    */
    'chunk_size_bytes' => (int) env('FILE_HOSTING_CHUNK_SIZE', 10 * 1024 * 1024),

    'multipart_threshold_bytes' => (int) env('FILE_HOSTING_MULTIPART_THRESHOLD', 10 * 1024 * 1024),

    'max_file_size_bytes' => (int) env('FILE_HOSTING_MAX_FILE_SIZE', 5 * 1024 * 1024 * 1024),

    'max_files_per_batch' => (int) env('FILE_HOSTING_MAX_FILES_PER_BATCH', 20),

    'max_concurrent_uploads' => (int) env('FILE_HOSTING_MAX_CONCURRENT', 2),

    'presign_ttl_minutes' => (int) env('FILE_HOSTING_PRESIGN_TTL', 15),

    'session_ttl_hours' => (int) env('FILE_HOSTING_SESSION_TTL', 24),

    /*
    |--------------------------------------------------------------------------
    | Extra grace (hours) after a pending upload session expires before the
    | scheduler (`file-hosting:prune-stale-uploads`) aborts it and reclaims the
    | orphaned multipart storage. Providers like Backblaze offer no S3 lifecycle
    | for incomplete multiparts, so this is the cleanup path. Keep >= a bit so a
    | just-expired session a user is actively resuming is not yanked mid-retry.
    |--------------------------------------------------------------------------
    */
    'stale_upload_grace_hours' => (int) env('FILE_HOSTING_STALE_UPLOAD_GRACE', 6),

    /*
    |--------------------------------------------------------------------------
    | Orphan-by-inactivity window (minutes). A pending session with no part
    | activity (no recordParts/complete) for this long is aborted even if its
    | 24h TTL has not elapsed — catches cancel/tab-close-right-after-init that
    | would otherwise hold a max_concurrent_uploads slot until expiry. A live
    | upload refreshes updated_at on every landed part, so 60 min of true
    | silence means it is genuinely dead. A pruned-but-actually-live slow part
    | resumes on the user's next attempt via content_hash.
    |--------------------------------------------------------------------------
    */
    'orphan_inactivity_minutes' => (int) env('FILE_HOSTING_ORPHAN_INACTIVITY_MINUTES', 60),

    'max_presigned_parts_per_response' => (int) env('FILE_HOSTING_MAX_PRESIGNED_PARTS', 100),

    /*
    |--------------------------------------------------------------------------
    | Default per-user storage quota (bytes). 0 = unlimited.
    |--------------------------------------------------------------------------
    */
    'default_quota_bytes' => (int) env('FILE_HOSTING_DEFAULT_QUOTA_BYTES', 5 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Owned team workspaces included with any active paid FH storage plan.
    | Extra slots via catalog file-hosting.addon.workspace-slot.monthly (+1 each).
    |--------------------------------------------------------------------------
    */
    'base_owned_workspace_slots' => (int) env(
        'FILE_HOSTING_BASE_OWNED_WORKSPACE_SLOTS',
        FileHostingCatalog::BASE_OWNED_WORKSPACE_SLOTS,
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME prefixes (empty = allow all non-empty mime from client).
    |--------------------------------------------------------------------------
    */
    'allowed_mime_prefixes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FILE_HOSTING_ALLOWED_MIME_PREFIXES', 'image/,application/,text/,audio/,video/')),
    ))),

    'signed_download_ttl_minutes' => (int) env('FILE_HOSTING_SIGNED_DOWNLOAD_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Treat cached signed URLs as expired this many seconds early.
    |--------------------------------------------------------------------------
    */
    'signed_download_cache_margin_seconds' => (int) env('FILE_HOSTING_SIGNED_DOWNLOAD_CACHE_MARGIN', 60),

    /*
    |--------------------------------------------------------------------------
    | Laravel temporary signed URL TTL for guest web download hop
    | (/s/f/{id}/download). Blocks unsigned direct download attempts.
    |--------------------------------------------------------------------------
    */
    'public_download_signature_ttl_minutes' => (int) env('FILE_HOSTING_PUBLIC_DOWNLOAD_SIGNATURE_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Optional Cloudflare Worker byte proxy (hybrid upload + download).
    | Empty base_url → browser-direct S3 (legacy path for parts + downloads).
    | When base_url + token + disk alias are set:
    |   - Upload: Worker URLs for PUT/UploadPart; create/complete/abort stay SDK.
    |   - Download: HMAC Worker GetObject URLs (Bandwidth Alliance for B2);
    |     cached in signed_download_url instead of a direct S3 temporary URL.
    | Alias values must match Worker S3_TARGETS.targets keys.
    | Token is upload Bearer + download HMAC secret (never put raw token in query).
    |
    | FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM is a legacy fallback for CE.
    |--------------------------------------------------------------------------
    */
    'upload_proxy' => [
        'base_url' => rtrim((string) env('FILE_HOSTING_UPLOAD_PROXY_URL', ''), '/'),
        'token' => (string) env('FILE_HOSTING_UPLOAD_PROXY_TOKEN', ''),
        'disk_targets' => array_filter([
            's3FileHostingCe' => env(
                'FILE_HOSTING_UPLOAD_PROXY_TARGET_CE',
                env('FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM'),
            ),
            's3FileHostingUs' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_US'),
            's3FileHostingEu' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_EU'),
            // Legacy HostedFile.disk / upload session values.
            's3Custom' => env(
                'FILE_HOSTING_UPLOAD_PROXY_TARGET_CE',
                env('FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM'),
            ),
        ]),
    ],

];
