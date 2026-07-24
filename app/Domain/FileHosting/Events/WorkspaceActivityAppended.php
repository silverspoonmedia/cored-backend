<?php

namespace App\Domain\FileHosting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Invalidation ping for the team activity feed (client soft-refetches).
 */
class WorkspaceActivityAppended implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly ?string $actorId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('file-hosting.workspace.'.$this->workspaceId)];
    }

    public function broadcastAs(): string
    {
        return 'activity.appended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'actor_id' => $this->actorId,
        ];
    }
}
