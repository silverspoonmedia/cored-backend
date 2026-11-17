<?php

namespace App\Domain\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment channel / route catalog changed (admin toggle or policy sync).
 *
 * Routed to private-payments.routing. Checkout pages refetch channel list + fees.
 */
class PaymentRoutingCatalogChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $revision,
        public readonly string $reason,
        public readonly ?string $channelCode = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('payments.routing')];
    }

    public function broadcastAs(): string
    {
        return 'routing.catalog.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'revision' => $this->revision,
            'reason' => $this->reason,
            'channel_code' => $this->channelCode,
        ];
    }
}
