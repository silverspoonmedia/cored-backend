<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Events\ManagementUpdated;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire management realtime events after successful mutations.
 */
class VtualManagementRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    /**
     * @param  string  $kind  management|member|invite|creator|event
     * @param  string  $action  created|updated|deleted|accepted|declined|cancelled|revoked|assigned|unassigned
     */
    public function changed(string $managementId, string $kind, ?string $refId, string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ManagementUpdated($managementId, $kind, $refId, $action));
    }
}
