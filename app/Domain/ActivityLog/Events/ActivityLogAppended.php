<?php

namespace App\Domain\ActivityLog\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * New activity log entry appended — admin activity log list soft-refetch.
 * Ids-only payload: log name + event, no properties (may contain sensitive data).
 */
class ActivityLogAppended implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $logName,
        public readonly string $event,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('logs.activity')];
    }

    public function broadcastAs(): string
    {
        return 'log.activity.appended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'log_name' => $this->logName,
            'event' => $this->event,
        ];
    }
}
