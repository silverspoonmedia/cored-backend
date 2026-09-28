<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A management org, its members, invites, or creator assignments changed.
 *
 * Payload is ids + kind + action only — never member/user details.
 */
class ManagementUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $kind  management|member|invite|creator|creator_grant|event|creator_invite
     * @param  string  $action  created|updated|deleted|accepted|declined|cancelled|revoked|assigned|unassigned|granted
     */
    public function __construct(
        public readonly string $managementId,
        public readonly string $kind,
        public readonly ?string $refId,
        public readonly string $action,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.management.'.$this->managementId)];
    }

    public function broadcastAs(): string
    {
        return 'management.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'management_id' => $this->managementId,
            'kind' => $this->kind,
            'ref_id' => $this->refId,
            'action' => $this->action,
        ];
    }
}
