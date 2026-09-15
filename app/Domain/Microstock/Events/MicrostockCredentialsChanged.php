<?php

namespace App\Domain\Microstock\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A marketplace credential changed — owner's credential list soft-refetch.
 * No secret data in payload — ids-only.
 */
class MicrostockCredentialsChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $ownerUserId,
        public readonly string $action,
        public readonly ?string $credentialId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('archivd.user.'.$this->ownerUserId)];
    }

    public function broadcastAs(): string
    {
        return 'microstock.credentials.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'credential_id' => $this->credentialId,
        ];
    }
}
