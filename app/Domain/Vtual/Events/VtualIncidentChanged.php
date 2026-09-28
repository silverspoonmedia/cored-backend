<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff incident queue changed (recorded / acknowledged / resolved).
 *
 * Payload is ids + enums only. The incident `title` and `detail` are free text
 * assembled from platform errors and can carry channel handles, URLs or a
 * redacted-but-still-informative fragment — the triage list refetches instead.
 */
class VtualIncidentChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $incidentId = null,
        public readonly ?string $kind = null,
        public readonly ?string $severity = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.incidents')];
    }

    public function broadcastAs(): string
    {
        return 'incident.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'incident_id' => $this->incidentId,
            'kind' => $this->kind,
            'severity' => $this->severity,
            'status' => $this->status,
        ];
    }
}
