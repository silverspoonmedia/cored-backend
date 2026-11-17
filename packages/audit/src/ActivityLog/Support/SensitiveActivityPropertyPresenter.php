<?php

namespace App\Domain\ActivityLog\Support;

use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Throwable;

/**
 * Decrypts envelope-encrypted PII inside `activity_log.properties` for API
 * responses (read path). Database rows keep the ciphertext.
 *
 * Authorized viewers (`permission:view activity log`, workspace members) see
 * the original plaintext — the API/SPA contract does not change.
 */
class SensitiveActivityPropertyPresenter
{
    /** Sentinel used when a stored envelope cannot be decrypted (e.g. rotated KEK). */
    public const UNAVAILABLE = '[unavailable]';

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function forApi(array $properties, ?string $activityId): array
    {
        if (! SensitiveActivityProperties::enabled()) {
            return $properties;
        }

        foreach (SensitiveActivityProperties::keys() as $key) {
            $value = $properties[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            // Legacy plaintext row (not backfilled yet) — pass through untouched.
            if (! EncryptedEnvelope::isEnvelopeJson($value)) {
                continue;
            }

            $properties[$key] = self::decrypt($value, $key, $activityId);
        }

        return $properties;
    }

    private static function decrypt(string $value, string $key, ?string $activityId): string
    {
        try {
            return app(EnvelopeEncryptionService::class)->decrypt(
                $value,
                SensitiveActivityProperties::context(
                    $key,
                    $activityId,
                    SensitiveActivityProperties::PURPOSE_READ,
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return self::UNAVAILABLE;
        }
    }
}
