<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff platform-credential list may have changed (test / revoke / CRUD).
 *
 * Payload is ids + status only — never secrets or runtime tokens.
 */
class PlatformCredentialChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $platformId,
        public readonly ?string $credentialId,
        public readonly string $action,
        public readonly ?string $status = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.platform-credentials')];
    }

    public function broadcastAs(): string
    {
        return 'platform.credential.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'platform_id' => $this->platformId,
            'credential_id' => $this->credentialId,
            'action' => $this->action,
            'status' => $this->status,
        ];
    }
}
