<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\ActivityLog\Events\ActivityLogAppended;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire ActivityLog realtime events after recording.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only.
 */
class ActivityLogRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function appended(string $logName, string $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ActivityLogAppended($logName, $event));
    }
}
