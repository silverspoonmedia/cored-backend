<?php

namespace App\Domain\Archivd\Support;

/**
 * sha256 hex helper for the DMCA hash blocklist (CLI, FormRequest, service).
 */
final class Sha256Checksum
{
    public const PATTERN = '/^[0-9a-f]{64}$/';

    public static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($value));
    }
}
