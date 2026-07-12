<?php

namespace App\Domain\ActivityLog\Support;

use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit trail for envelope decryption (log_name: crypto).
 *
 * Called only from {@see EnvelopeEncryptionService}.
 */
class CryptoActivityRecorder
{
    /** @var array<int, string> */
    private const ALLOWED_PROPERTY_KEYS = [
        'resource_type',
        'resource_id',
        'field',
        'purpose',
        'kms_key_id',
        'ip_address',
        'cache_hit',
        'envelope_version',
        'legacy_ciphertext',
    ];

    /** @var array<int, string> */
    private const FORBIDDEN_PROPERTY_KEYS = [
        'plaintext',
        'ciphertext',
        'dek',
        'dek_ciphertext',
        'wrapped_dek',
        'iv',
        'tag',
        'secret',
        'token',
    ];

    public function recordFieldDecrypted(
        string $resourceType,
        ?string $resourceId,
        string $field,
        ?string $purpose = null,
        ?string $kmsKeyId = null,
        ?bool $cacheHit = null,
        ?int $envelopeVersion = null,
        ?bool $legacyCiphertext = null,
        array $extraProperties = [],
    ): void {
        $properties = array_merge([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'field' => $field,
            'purpose' => $purpose,
            'kms_key_id' => $kmsKeyId,
            'ip_address' => request()->ip(),
            'cache_hit' => $cacheHit,
            'envelope_version' => $envelopeVersion,
            'legacy_ciphertext' => $legacyCiphertext,
        ], $this->filterExtraProperties($extraProperties));

        $properties = array_filter(
            $properties,
            fn (mixed $value): bool => $value !== null,
        );

        $logger = activity()
            ->useLog('crypto')
            ->event('field_decrypted')
            ->withProperties($properties);

        $causer = Auth::guard(config('auth.defaults.guard'))->user();

        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        }

        $logger->log('field_decrypted');
    }

    /**
     * @param  array<string, mixed>  $extraProperties
     * @return array<string, mixed>
     */
    private function filterExtraProperties(array $extraProperties): array
    {
        $filtered = [];

        foreach ($extraProperties as $key => $value) {
            $key = (string) $key;

            if (in_array($key, self::FORBIDDEN_PROPERTY_KEYS, true)) {
                continue;
            }

            if (! in_array($key, self::ALLOWED_PROPERTY_KEYS, true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
