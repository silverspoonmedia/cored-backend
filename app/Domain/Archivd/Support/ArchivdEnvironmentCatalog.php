<?php

namespace App\Domain\Archivd\Support;

use App\Support\EnvironmentVariableCatalog;

/**
 * Archivd's environment keys, in the kernel's typed catalog.
 *
 * `.env.example` declares one storage disk and one upload proxy per product, and
 * the kernel's template validation needs their types. Those keys are product
 * knowledge, so Archivd publishes them from its provider instead of the kernel
 * listing them — removing the product removes the keys with it.
 */
final class ArchivdEnvironmentCatalog
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'ARCHIVD_DISK' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_DIRECTORY' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_REGION_CE_DISK' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_REGION_US_DISK' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_BUCKET' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_REGION' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_KEY' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_SECRET' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_ENDPOINT' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_DOMAIN' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_S3_CE_USE_PATH_STYLE' => EnvironmentVariableCatalog::TYPE_BOOL,
            'ARCHIVD_UPLOAD_PROXY_URL' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_UPLOAD_PROXY_TOKEN' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_UPLOAD_PROXY_TARGET_CE' => EnvironmentVariableCatalog::TYPE_STRING,
            'ARCHIVD_UPLOAD_PROXY_TARGET_US' => EnvironmentVariableCatalog::TYPE_STRING,
        ];
    }
}
