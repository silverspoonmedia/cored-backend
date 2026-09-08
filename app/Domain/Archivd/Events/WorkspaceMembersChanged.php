<?php

namespace App\Domain\Archivd\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Team membership / archive signal for the workspace channel.
 */
class WorkspaceMembersChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $action,
        public readonly ?string $userId = null,
        public readonly ?string $actorId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('archivd.workspace.'.$this->workspaceId)];
    }

    public function broadcastAs(): string
    {
        return 'members.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'action' => $this->action,
            'user_id' => $this->userId,
            'actor_id' => $this->actorId,
        ];
    }
}
