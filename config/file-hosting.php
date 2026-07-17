<?php

return [

    'disk' => env('FILE_HOSTING_DISK', 's3Custom'),

    /*
    |--------------------------------------------------------------------------
    | Upload regions — region key → disk name.
    | Only regions with a non-empty disk appear in the upload dropdown.
    | Add a region: set FILE_HOSTING_REGION_XX_DISK env + define the disk
    | in config/filesystems.php. No code change required.
    |--------------------------------------------------------------------------
    */
    'regions' => array_filter([
        'sea' => env('FILE_HOSTING_REGION_SEA_DISK', 's3Custom'),
        'us'  => env('FILE_HOSTING_REGION_US_DISK'),
        'eu'  => env('FILE_HOSTING_REGION_EU_DISK'),
    ]),

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

    'max_presigned_parts_per_response' => (int) env('FILE_HOSTING_MAX_PRESIGNED_PARTS', 100),

    /*
    |--------------------------------------------------------------------------
    | Default per-user storage quota (bytes). 0 = unlimited.
    |--------------------------------------------------------------------------
    */
    'default_quota_bytes' => (int) env('FILE_HOSTING_DEFAULT_QUOTA_BYTES', 5 * 1024 * 1024 * 1024),

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
    | Optional Cloudflare Worker upload proxy (hybrid mode).
    | Empty base_url → browser-direct S3 presign (legacy path).
    | When base_url + token + disk alias are set, Monolith returns Worker
    | URLs for PUT/UploadPart only; create/complete/abort stay on the server SDK.
    | Alias values must match Worker S3_TARGETS.targets keys.
    |--------------------------------------------------------------------------
    */
    'upload_proxy' => [
        'base_url' => rtrim((string) env('FILE_HOSTING_UPLOAD_PROXY_URL', ''), '/'),
        'token' => (string) env('FILE_HOSTING_UPLOAD_PROXY_TOKEN', ''),
        'disk_targets' => array_filter([
            's3Custom' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM'),
            's3Customeu' => env('FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOMEU'),
        ]),
    ],

];
