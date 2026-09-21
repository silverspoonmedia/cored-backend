<?php

namespace App\Domain\Ai\Support;

use App\Domain\Ai\Events\AiConnectionChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire AI-connection realtime events after successful mutations.
 */
class AiConnectionRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(
        string $action,
        ?string $connectionId = null,
        ?string $slug = null,
        ?string $status = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        event(new AiConnectionChanged($action, $connectionId, $slug, $status));
    }
}
