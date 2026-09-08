<?php

namespace App\Domain\Microstock\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Collection-level AI analysis progress for the Microstock toolbar banner.
 */
class CollectionAnalysisProgress implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{status: string, phase: string, billing_tier: string|null, hint: string|null, csv_path: string|null, total: int, processed: int, finalized: int, failed: int}  $snapshot
     */
    public function __construct(
        public readonly string $folderId,
        public readonly string $ownerUserId,
        public readonly ?string $workspaceId,
        public readonly array $snapshot,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->workspaceId !== null && $this->workspaceId !== '') {
            return [new PrivateChannel('archivd.workspace.'.$this->workspaceId)];
        }

        return [new PrivateChannel('archivd.user.'.$this->ownerUserId)];
    }

    public function broadcastAs(): string
    {
        return 'collection.analysis.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'folder_id' => $this->folderId,
            'workspace_id' => $this->workspaceId,
            'owner_user_id' => $this->ownerUserId,
            'status' => $this->snapshot['status'],
            'phase' => $this->snapshot['phase'],
            'billing_tier' => $this->snapshot['billing_tier'],
            'hint' => $this->snapshot['hint'],
            'csv_path' => $this->snapshot['csv_path'],
            'total' => $this->snapshot['total'],
            'processed' => $this->snapshot['processed'],
            'finalized' => $this->snapshot['finalized'],
            'failed' => $this->snapshot['failed'],
        ];
    }
}
