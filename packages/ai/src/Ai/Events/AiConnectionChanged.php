<?php

namespace App\Domain\Ai\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An AI connection row changed (created/updated/tested/deleted/default).
 * Payload carries ids + status only — never the endpoint key.
 */
class AiConnectionChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $connectionId = null,
        public readonly ?string $slug = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('ai.connections')];
    }

    public function broadcastAs(): string
    {
        return 'ai.connection.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'connection_id' => $this->connectionId,
            'slug' => $this->slug,
            'status' => $this->status,
        ];
    }
}
