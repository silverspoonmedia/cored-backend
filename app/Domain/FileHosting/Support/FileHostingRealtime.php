<?php

namespace App\Domain\FileHosting\Support;

use App\Domain\FileHosting\Events\FileStatsChanged;
use App\Domain\FileHosting\Events\ListingChanged;
use App\Domain\FileHosting\Events\QuotaChanged;
use App\Domain\FileHosting\Events\WorkspaceActivityAppended;
use App\Domain\FileHosting\Events\WorkspaceMembersChanged;
use App\Domain\FileHosting\Http\Resources\FolderResource;
use App\Domain\FileHosting\Http\Resources\HostedFileResource;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Fire File Hosting realtime events after successful mutations.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only — independent of
 * APP_NOTIFICATION_TYPE so listing can stay live while the bell polls.
 */
class FileHostingRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    /**
     * Client-safe Echo options for Blade FH pages (null when broadcast unconfigured).
     *
     * @return array<string, mixed>|null
     */
    public function clientConfig(): ?array
    {
        return BroadcastConnection::socketClientConfig();
    }

    /**
     * @param  array<string, mixed>|null  $resource
     * @param  list<string>|null  $entityIds
     */
    public function listingChanged(
        string $action,
        string $entityType,
        ?string $entityId,
        string $ownerUserId,
        ?string $workspaceId,
        ?string $folderId,
        ?string $fromFolderId = null,
        ?string $actorId = null,
        ?array $entityIds = null,
        ?array $resource = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        event(new ListingChanged(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            ownerUserId: $ownerUserId,
            workspaceId: $workspaceId,
            folderId: $folderId,
            fromFolderId: $fromFolderId,
            actorId: $actorId,
            entityIds: $entityIds,
            resource: $resource,
        ));
    }

    public function fileListing(
        string $action,
        HostedFile $file,
        User $actor,
        ?string $fromFolderId = null,
        bool $includeResource = true,
    ): void {
        $this->listingChanged(
            action: $action,
            entityType: 'file',
            entityId: $file->id,
            ownerUserId: (string) $file->user_id,
            workspaceId: $file->workspace_id,
            folderId: $file->folder_id,
            fromFolderId: $fromFolderId,
            actorId: $actor->id,
            resource: $includeResource ? $this->fileResource($file) : null,
        );

        if ($file->workspace_id !== null) {
            $this->activityAppended((string) $file->workspace_id, $actor->id);
        }
    }

    public function folderListing(
        string $action,
        Folder $folder,
        User $actor,
        ?string $fromFolderId = null,
        bool $includeResource = true,
    ): void {
        $this->listingChanged(
            action: $action,
            entityType: 'folder',
            entityId: $folder->id,
            ownerUserId: (string) $folder->user_id,
            workspaceId: $folder->workspace_id,
            folderId: $folder->parent_id,
            fromFolderId: $fromFolderId,
            actorId: $actor->id,
            resource: $includeResource ? $this->folderResource($folder) : null,
        );

        if ($folder->workspace_id !== null) {
            $this->activityAppended((string) $folder->workspace_id, $actor->id);
        }
    }

    /**
     * @param  list<string>  $entityIds
     */
    public function bulkListing(
        string $action,
        string $entityType,
        array $entityIds,
        string $ownerUserId,
        ?string $workspaceId,
        User $actor,
    ): void {
        $this->listingChanged(
            action: $action,
            entityType: $entityType,
            entityId: null,
            ownerUserId: $ownerUserId,
            workspaceId: $workspaceId,
            folderId: null,
            actorId: $actor->id,
            entityIds: array_values($entityIds),
            resource: null,
        );

        if ($workspaceId !== null && $workspaceId !== '') {
            $this->activityAppended($workspaceId, $actor->id);
        }
    }

    public function quotaChanged(string $ownerUserId): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new QuotaChanged($ownerUserId));
    }

    public function membersChanged(
        string $workspaceId,
        string $action,
        ?string $userId = null,
        ?string $actorId = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        event(new WorkspaceMembersChanged($workspaceId, $action, $userId, $actorId));
    }

    public function activityAppended(string $workspaceId, ?string $actorId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new WorkspaceActivityAppended($workspaceId, $actorId));
    }

    public function fileStats(HostedFile $file): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new FileStatsChanged(
            fileId: (string) $file->id,
            ownerUserId: (string) $file->user_id,
            workspaceId: $file->workspace_id,
            viewCount: (int) $file->view_count,
            downloadCount: (int) $file->download_count,
        ));
    }

    /**
     * Broadcast at most one stats event per file per throttle window. The counter
     * itself is written synchronously by the caller; only the realtime push is
     * rate-limited so a hot file does not flood subscribers on every download.
     */
    public function fileStatsThrottled(HostedFile $file, int $windowSeconds = 5): void
    {
        if (! $this->enabled()) {
            return;
        }

        $cacheKey = 'filehosting:stats-broadcast:'.(string) $file->id;
        if (! Cache::add($cacheKey, 1, $windowSeconds)) {
            return;
        }

        $this->fileStats($file);
    }

    /**
     * @return array<string, mixed>
     */
    public function fileResource(HostedFile $file): array
    {
        return (new HostedFileResource($file))->resolve(Request::create('/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function folderResource(Folder $folder): array
    {
        return (new FolderResource($folder))->resolve(Request::create('/'));
    }
}
