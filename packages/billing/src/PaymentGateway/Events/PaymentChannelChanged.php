<?php

namespace App\Domain\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment channel row changed (enable/disable) — admin channel list soft-refetch.
 */
class PaymentChannelChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $channelCode = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('payments.admin')];
    }

    public function broadcastAs(): string
    {
        return 'payments.channel.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'channel_code' => $this->channelCode,
        ];
    }
}
