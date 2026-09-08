<?php

namespace App\Domain\Archivd\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * View/download counters for the file show page (not listing).
 */
class FileStatsChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $fileId,
        public readonly string $ownerUserId,
        public readonly ?string $workspaceId,
        public readonly int $viewCount,
        public readonly int $downloadCount,
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
        return 'file.stats.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->fileId,
            'owner_user_id' => $this->ownerUserId,
            'workspace_id' => $this->workspaceId,
            'view_count' => $this->viewCount,
            'download_count' => $this->downloadCount,
        ];
    }
}
