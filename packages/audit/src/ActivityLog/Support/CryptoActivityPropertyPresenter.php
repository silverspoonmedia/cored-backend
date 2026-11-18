<?php

namespace App\Domain\ActivityLog\Support;

/**
 * Transforms crypto activity properties for API responses (DB rows stay unchanged).
 */
class CryptoActivityPropertyPresenter
{
    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function forApi(array $properties): array
    {
        if (isset($properties['kms_key_id']) && is_string($properties['kms_key_id'])) {
            $properties['kms_key_id'] = self::maskKmsKeyId($properties['kms_key_id']);
        }

        return $properties;
    }

    public static function maskKmsKeyId(?string $kmsKeyId): ?string
    {
        if ($kmsKeyId === null || $kmsKeyId === '' || $kmsKeyId === 'local') {
            return $kmsKeyId;
        }

        if (strlen($kmsKeyId) <= 16) {
            return '***'.substr($kmsKeyId, -4);
        }

        return substr($kmsKeyId, 0, 10).'...'.substr($kmsKeyId, -8);
    }
}
