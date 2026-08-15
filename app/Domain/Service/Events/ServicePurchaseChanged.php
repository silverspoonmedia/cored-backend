<?php

namespace App\Domain\Service\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Service purchase status may have changed — clients soft-refetch.
 */
class ServicePurchaseChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $purchaseId,
        public readonly string $action,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('services.purchase.'.$this->purchaseId)];
    }

    public function broadcastAs(): string
    {
        return 'service.purchase.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'purchase_id' => $this->purchaseId,
            'action' => $this->action,
        ];
    }
}
