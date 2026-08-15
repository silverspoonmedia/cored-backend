<?php

namespace App\Domain\Service\Support;

use App\Domain\Service\Events\ServiceCatalogChanged;
use App\Domain\Service\Events\ServicePurchaseChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire Service realtime events after successful mutations.
 */
class ServiceRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function catalogChanged(string $action, ?string $serviceId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ServiceCatalogChanged($action, $serviceId));
    }

    public function purchaseChanged(string $purchaseId, string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ServicePurchaseChanged($purchaseId, $action));
    }
}
