<?php

/**
 * Resolve env value by primary key, optional fallback key, then default.
 */
$envFirst = static function (mixed $primary, ?string $fallback = null, mixed $default = null): mixed {
    if (is_string($primary) && $primary !== '') {
        $value = env($primary);
        if ($value !== null) {
            return $value;
        }
    }
    if ($fallback !== null && $fallback !== '') {
        $fallbackValue = env($fallback);
        if ($fallbackValue !== null) {
            return $fallbackValue;
        }
    }

    return $default;
};

/**
 * Build an S3-compatible disk definition. All credential fields are the NAMES
 * of env vars (resolved lazily via $envFirst), not their values.
 *
 * @param  array{bucket: string, region: string, key: string, secret: string, endpoint: string, domain: string, path_style: string, path_style_default?: bool}  $primary
 * @param  array{bucket?: string, region?: string, key?: string, secret?: string, endpoint?: string, domain?: string, path_style?: string}  $fallback
 * @return array<string, mixed>
 */
$s3CompatibleDisk = static function (array $primary, array $fallback = []) use ($envFirst): array {
    $pathStyleDefault = $primary['path_style_default'] ?? true;
    $pathStyleRaw = $envFirst(
        $primary['path_style'],
        $fallback['path_style'] ?? null,
        $pathStyleDefault,
    );

    return [
        'driver' => 's3',
        'bucket' => $envFirst($primary['bucket'], $fallback['bucket'] ?? null, ''),
        'region' => $envFirst($primary['region'], $fallback['region'] ?? null, 'auto'),
        'key' => $envFirst($primary['key'], $fallback['key'] ?? null),
        'secret' => $envFirst($primary['secret'], $fallback['secret'] ?? null),
        'endpoint' => $envFirst($primary['endpoint'], $fallback['endpoint'] ?? null),
        // B2 (and many S3-compatible APIs) need path-style so Create/Complete
        // match CF Worker forcePathStyle uploads on the same UploadId.
        'use_path_style_endpoint' => filter_var($pathStyleRaw, FILTER_VALIDATE_BOOL),
        'visibility' => 'public',
        'throw' => true,
        'domain' => $envFirst($primary['domain'], $fallback['domain'] ?? null),
    ];
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | A private S3-compatible bucket for the host's own direct uploads.
        | A product that owns its storage publishes its own disks from its
        | provider; this one template disk is what the upload proxy targets.
        */
        's3Private' => $s3CompatibleDisk([
            'bucket' => 'S3_PRIVATE_BUCKET',
            'region' => 'S3_PRIVATE_REGION',
            'key' => 'S3_PRIVATE_KEY',
            'secret' => 'S3_PRIVATE_SECRET',
            'endpoint' => 'S3_PRIVATE_ENDPOINT',
            'domain' => 'S3_PRIVATE_DOMAIN',
            'path_style' => 'S3_PRIVATE_USE_PATH_STYLE',
            'path_style_default' => true,
        ]),

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
