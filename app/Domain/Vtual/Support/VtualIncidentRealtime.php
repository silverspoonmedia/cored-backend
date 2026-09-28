<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Events\VtualIncidentChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire vTual incident realtime events after a successful mutation.
 */
class VtualIncidentRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(
        string $action,
        ?string $incidentId = null,
        IncidentKind|string|null $kind = null,
        IncidentSeverity|string|null $severity = null,
        IncidentStatus|string|null $status = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        event(new VtualIncidentChanged(
            $action,
            $incidentId,
            $kind instanceof IncidentKind ? $kind->value : $kind,
            $severity instanceof IncidentSeverity ? $severity->value : $severity,
            $status instanceof IncidentStatus ? $status->value : $status,
        ));
    }
}
