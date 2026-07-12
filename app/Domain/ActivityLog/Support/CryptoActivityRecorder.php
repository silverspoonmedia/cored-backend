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
    /**
     * @param  array<string, mixed>  $extraProperties
     */
    public function recordFieldDecrypted(
        string $resourceType,
        ?string $resourceId,
        string $field,
        ?string $purpose = null,
        ?string $kmsKeyId = null,
        array $extraProperties = [],
    ): void {
        $properties = array_merge([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'field' => $field,
            'purpose' => $purpose,
            'kms_key_id' => $kmsKeyId,
            'ip_address' => request()->ip(),
        ], $extraProperties);

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
}
