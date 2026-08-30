<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Events\PlatformCredentialChanged;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire platform-credential realtime events after successful mutations.
 */
class VtualPlatformCredentialRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(
        string $platformId,
        ?string $credentialId,
        string $action,
        PlatformCredentialStatus|string|null $status = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $statusValue = $status instanceof PlatformCredentialStatus
            ? $status->value
            : $status;

        event(new PlatformCredentialChanged($platformId, $credentialId, $action, $statusValue));
    }

    public function fromCredential(PlatformCredential $credential, string $action): void
    {
        $this->changed(
            (string) $credential->platform_id,
            (string) $credential->id,
            $action,
            $credential->status,
        );
    }
}
