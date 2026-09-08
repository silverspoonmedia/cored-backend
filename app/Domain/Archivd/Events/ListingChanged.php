<?php

namespace App\Domain\Archivd\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Metadata change for a personal or team folder listing.
 *
 * Routed to private-archivd.user.{owner} (personal) or
 * private-archivd.workspace.{id} (team). Does not touch the notification bell.
 */
class ListingChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>|null  $entityIds  Bulk entity ids when action is bulk_*
     * @param  array<string, mixed>|null  $resource  Single-entity snapshot for DOM patch
     */
    public function __construct(
        public readonly string $action,
        public readonly string $entityType,
        public readonly ?string $entityId,
        public readonly string $ownerUserId,
        public readonly ?string $workspaceId,
        public readonly ?string $folderId,
        public readonly ?string $fromFolderId = null,
        public readonly ?string $actorId = null,
        public readonly ?array $entityIds = null,
        public readonly ?array $resource = null,
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
        return 'listing.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'entity_ids' => $this->entityIds,
            'owner_user_id' => $this->ownerUserId,
            'workspace_id' => $this->workspaceId,
            'folder_id' => $this->folderId,
            'from_folder_id' => $this->fromFolderId,
            'actor_id' => $this->actorId,
            'resource' => $this->resource,
        ];
    }
}
