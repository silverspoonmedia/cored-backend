<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Creator claim lifecycle progress for the claim page WebSocket client.
 */
class CreatorClaimProgressChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $claim
     */
    public function __construct(
        public readonly string $claimId,
        public readonly string $phase,
        public readonly array $claim,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.claim.'.$this->claimId)];
    }

    public function broadcastAs(): string
    {
        return 'claim.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'phase' => $this->phase,
            'claim' => $this->claim,
        ];
    }
}
