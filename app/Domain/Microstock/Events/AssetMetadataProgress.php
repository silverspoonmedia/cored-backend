<?php

namespace App\Domain\Microstock\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Per-file Microstock metadata progress for listing badges and review panel.
 */
class AssetMetadataProgress implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{title: string|null, keywords: list<string>, status: string|null, stages: array<string, string>, phase: string|null, error: string|null}  $microstock
     */
    public function __construct(
        public readonly string $fileId,
        public readonly ?string $folderId,
        public readonly string $ownerUserId,
        public readonly ?string $workspaceId,
        public readonly array $microstock,
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
        return 'asset.metadata.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->fileId,
            'folder_id' => $this->folderId,
            'workspace_id' => $this->workspaceId,
            'owner_user_id' => $this->ownerUserId,
            'microstock' => $this->microstock,
        ];
    }
}
