<?php

namespace App\Domain\Inference\Support;

use App\Domain\Inference\Events\InferenceCatalogChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire Inference realtime events after successful mutations.
 */
class InferenceRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function catalogChanged(string $entity, string $action, ?string $entityId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new InferenceCatalogChanged($entity, $action, $entityId));
    }
}
