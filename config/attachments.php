<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Direct upload target disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where browser-direct uploads are staged. Defaults to
    | the playground order attachment disk (s3Custom / S3-compatible).
    |
    */

    'disk' => env('ATTACHMENT_UPLOAD_DISK', 's3Custom'),

    /*
    |--------------------------------------------------------------------------
    | Object key prefix
    |--------------------------------------------------------------------------
    */

    'directory' => env('ATTACHMENT_UPLOAD_DIRECTORY', 'orders/attachments'),

    /*
    |--------------------------------------------------------------------------
    | Chunk / multipart settings
    |--------------------------------------------------------------------------
    |
    | S3 multipart parts (except the last) must be at least 5 MiB. Files below
    | multipart_threshold_bytes receive a single presigned PUT instead.
    |
    */

    'chunk_size_bytes' => (int) env('ATTACHMENT_UPLOAD_CHUNK_SIZE', 8 * 1024 * 1024),

    'multipart_threshold_bytes' => (int) env('ATTACHMENT_UPLOAD_MULTIPART_THRESHOLD', 8 * 1024 * 1024),

    'max_file_size_bytes' => (int) env('ATTACHMENT_UPLOAD_MAX_FILE_SIZE', 5 * 1024 * 1024 * 1024),

    'max_files_per_order' => (int) env('ATTACHMENT_UPLOAD_MAX_FILES_PER_ORDER', 10),

    'max_concurrent_uploads' => (int) env('ATTACHMENT_UPLOAD_MAX_CONCURRENT', 2),

    'presign_ttl_minutes' => (int) env('ATTACHMENT_UPLOAD_PRESIGN_TTL', 15),

    'session_ttl_hours' => (int) env('ATTACHMENT_UPLOAD_SESSION_TTL', 24),

    /*
    |--------------------------------------------------------------------------
    | Maximum presigned part URLs returned in a single init/status response
    |--------------------------------------------------------------------------
    */

    'max_presigned_parts_per_response' => (int) env('ATTACHMENT_UPLOAD_MAX_PRESIGNED_PARTS', 100),

];
