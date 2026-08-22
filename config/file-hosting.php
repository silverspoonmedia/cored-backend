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
    | config/filesystems.php (s3FileHostingCe / Us or a custom disk).
    |--------------------------------------------------------------------------
    */
    'regions' => array_filter([
        'ce' => env('FILE_HOSTING_REGION_CE_DISK', 's3FileHostingCe'),
        'us' => env('FILE_HOSTING_REGION_US_DISK'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Optional disk name → canonical disk name (RegionResolver alias collapse).
    |--------------------------------------------------------------------------
    */
    'disk_aliases' => [],

    /*
    |--------------------------------------------------------------------------
    | Regions disabled for new uploads (still serve downloads).
    | Comma-separated region keys. Files already stored on a disabled
    | region's disk remain downloadable — only new uploads are blocked.
    |--------------------------------------------------------------------------
    */
    'disabled_regions' => [],

    'directory' => env('FILE_HOSTING_DIRECTORY', 'file-hosting'),

    /*
    |--------------------------------------------------------------------------
    | Browser part size. 8 MB is a precaution for ISP-shaped consumer links:
    | a stalled 8 MB part recovers ~3× faster than a stalled 25 MB part.
    | multipart_threshold_bytes: size < threshold → single PUT; else multipart.
    | Keep threshold == chunk_size so files 8-25 MB use multipart parallel parts.
    |--------------------------------------------------------------------------
    */
    'chunk_size_bytes' => 8 * 1024 * 1024,

    'multipart_threshold_bytes' => 8 * 1024 * 1024,

    'max_file_size_bytes' => 5 * 1024 * 1024 * 1024,

    'max_files_per_batch' => 20,

    'max_concurrent_uploads' => 2,

    /*
    |--------------------------------------------------------------------------
    | Concurrent part uploads within a single file (parallel XHR streams).
    | A strictly sequential single-stream upload collapses to per-connection
    | throughput on long files (the 20→10→1 MB/s decay); 3 parallel parts is the
    | sweet spot vs B2 + CF Worker Free limits. Lower to 2 if 520s increase.
    |--------------------------------------------------------------------------
    */
    'max_concurrent_parts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Presigned part-URL TTL (minutes). Init signs every part upfront, so a slow
    | upload can outlive a short TTL and hard-fail late parts with 403. 60 min
    | (SigV4 presign allows up to 7 days) + client re-sign-on-403 covers long
    | uploads. Was 15 — too short for multi-GB files at degraded speeds.
    |--------------------------------------------------------------------------
    */
    'presign_ttl_minutes' => 60,

    'session_ttl_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Extra grace (hours) after a pending upload session expires before the
    | scheduler (`file-hosting:prune-stale-uploads`) aborts it and reclaims the
    | orphaned multipart storage. Providers like Backblaze offer no S3 lifecycle
    | for incomplete multiparts, so this is the cleanup path. Keep >= a bit so a
    | just-expired session with parts still landing is not yanked mid-transfer.
    |--------------------------------------------------------------------------
    */
    'stale_upload_grace_hours' => 6,

    /*
    |--------------------------------------------------------------------------
    | Orphan-by-inactivity window (minutes). A pending multipart session with no
    | NEW part on S3 (ListParts LastModified) for this long is aborted even if its
    | 24h TTL has not elapsed — catches cancel/tab-close-right-after-init that
    | would otherwise hold a max_concurrent_uploads slot until expiry. Decision is
    | made on S3 truth, not DB updated_at: even the slowest live connection keeps
    | landing parts, so this window can be generous (default 3h). Resume is gone,
    | so a mistakenly-pruned live upload simply restarts on the next attempt.
    |--------------------------------------------------------------------------
    */
    'orphan_inactivity_minutes' => 180,

    'max_presigned_parts_per_response' => 100,

    /*
    |--------------------------------------------------------------------------
    | Default per-user storage quota (bytes). 0 = unlimited.
    |--------------------------------------------------------------------------
    */
    'default_quota_bytes' => 5 * 1024 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Owned team workspaces included with any active paid FH storage plan.
    | Extra slots via catalog file-hosting.addon.workspace-slot.monthly (+1 each).
    |--------------------------------------------------------------------------
    */
    'base_owned_workspace_slots' => FileHostingCatalog::BASE_OWNED_WORKSPACE_SLOTS,

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME prefixes (empty = allow all non-empty mime from client).
    |--------------------------------------------------------------------------
    */
    'allowed_mime_prefixes' => ['image/', 'application/', 'text/', 'audio/', 'video/'],

    /*
    |--------------------------------------------------------------------------
    | Signed download URL TTL. Cached per file row; a repeat download within
    | this window reuses the cached URL instead of reminting (HMAC or S3
    | presign). 60 minutes aligns with the upload presign TTL and cuts remints
    | for repeat downloads. Longer TTL = fewer mint writes, slightly longer
    | link validity.
    |--------------------------------------------------------------------------
    */
    'signed_download_ttl_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Treat cached signed URLs as expired this many seconds early.
    |--------------------------------------------------------------------------
    */
    'signed_download_cache_margin_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Laravel temporary signed URL TTL for guest web download hop
    | (/s/f/{id}/download). Blocks unsigned direct download attempts.
    |--------------------------------------------------------------------------
    */
    'public_download_signature_ttl_minutes' => 5,

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
    |--------------------------------------------------------------------------
    */
    'upload_proxy' => [
        'base_url' => rtrim((string) env('FILE_HOSTING_UPLOAD_PROXY_URL', ''), '/'),
        'token' => (string) env('FILE_HOSTING_UPLOAD_PROXY_TOKEN', ''),
        'disk_targets' => array_filter([
            's3FileHostingCe' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_CE'),
            's3FileHostingUs' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_US'),
        ]),
    ],

    'download_vault' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug logging — log upstream S3 exception detail (headers, command params)
    | when an upload fails. Never enable in production: headers may contain
    | sensitive values.
    |--------------------------------------------------------------------------
    */
    'debug_log_requests' => (bool) env('FILE_HOSTING_DEBUG_LOG_REQUESTS', false),

];
