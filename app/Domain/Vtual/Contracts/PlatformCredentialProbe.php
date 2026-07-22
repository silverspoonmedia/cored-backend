<?php

namespace App\Domain\Vtual\Contracts;

use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Support\PlatformCredentialTestResult;

interface PlatformCredentialProbe
{
    /**
     * Whether this probe handles the credential's platform + type.
     */
    public function supports(PlatformCredential $credential): bool;

    /**
     * Probe live API using admin secrets. Must not persist; caller writes runtime/status.
     *
     * @param  array<string, mixed>  $secretPayload  Decrypted admin secret_payload
     */
    public function probe(PlatformCredential $credential, array $secretPayload): PlatformCredentialTestResult;
}
