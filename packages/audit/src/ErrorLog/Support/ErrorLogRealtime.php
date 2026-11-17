<?php

namespace App\Domain\ErrorLog\Support;

use App\Domain\ErrorLog\Events\ErrorLogRecorded;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire ErrorLog realtime events after recording.
 */
class ErrorLogRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function recorded(string $level, string $type): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ErrorLogRecorded($level, $type));
    }
}
