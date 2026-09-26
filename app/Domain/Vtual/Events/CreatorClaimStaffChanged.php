<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff pending-claim queue may have changed (start / verify queued / accept
 * anyway / cancel).
 *
 * Payload is ids + action + status only — never a claim snapshot, which would
 * carry the owner-only bio token. The owner-facing channel
 * `vtual.claim.{claimId}` keeps the full snapshot; this one is the staff ping.
 */
class CreatorClaimStaffChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $claimId,
        public readonly string $creatorId,
        public readonly string $action,
        public readonly ?string $status = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.claims')];
    }

    public function broadcastAs(): string
    {
        return 'claim.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'claim_id' => $this->claimId,
            'creator_id' => $this->creatorId,
            'action' => $this->action,
            'status' => $this->status,
        ];
    }
}
