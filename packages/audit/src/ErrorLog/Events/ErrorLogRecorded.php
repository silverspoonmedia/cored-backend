<?php

namespace App\Domain\ErrorLog\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * New error log occurrence recorded — admin error log list soft-refetch.
 */
class ErrorLogRecorded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $level,
        public readonly string $type,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('logs.errors')];
    }

    public function broadcastAs(): string
    {
        return 'log.error.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'level' => $this->level,
            'type' => $this->type,
        ];
    }
}
