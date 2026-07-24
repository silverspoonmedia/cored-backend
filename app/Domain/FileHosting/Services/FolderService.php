<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FileHostingRealtime;
use App\Domain\FileHosting\Support\FolderAncestorChain;
use App\Domain\FileHosting\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly VisibilityService $visibility,
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceActivityRecorder $activity,
        private readonly FileHostingRealtime $realtime,
    ) {}

    /**
     * @return array{
     *     owner_id: string,
     *     workspace_id: string|null,
     *     folder: Folder|null,
     *     breadcrumb: list<array{id: string|null, name: string}>,
     *     folders: Collection<int, Folder>,
     *     files: Collection<int, HostedFile>
     * }
     */
    public function browse(User $actor, ?string $folderId, ?string $userId = null, ?string $workspaceId = null): array
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: false);
        $wsId = $workspace?->id;

        $folder = null;
        if ($folderId !== null) {
            $folder = $this->folders->findOwnedOrFail($ownerId, $folderId, $wsId);
        }

        $files = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $wsId)
            ->where('folder_id', $folderId)
            ->orderBy('name')
            ->get();

        $rootLabel = $workspace !== null
            ? $workspace->name
            : __('api/file-hosting.root_label');

        return [
            'owner_id' => $ownerId,
            'workspace_id' => $wsId,
            'folder' => $folder,
            'breadcrumb' => FolderAncestorChain::breadcrumb($folder, $rootLabel),
            'folders' => $this->folders->children($ownerId, $folderId, $wsId),
            'files' => $files,
        ];
    }

    /**
     * @return array{
     *     owner_id: string,
     *     folder: Folder,
     *     breadcrumb: list<array{id: string, name: string}>,
     *     folders: Collection<int, Folder>,
     *     files: Collection<int, HostedFile>
     * }
     */
    public function publicBrowse(string $folderId): array
    {
        $folder = Folder::query()->whereKey($folderId)->first();

        if ($folder === null) {
            throw new FileHostingException(__('api/file-hosting.folder_not_found'), 404);
        }

        $this->visibility->assertGuestCanReadFolder($folder);

        $files = HostedFile::query()
            ->where('user_id', $folder->user_id)
            ->where('workspace_id', $folder->workspace_id)
            ->where('folder_id', $folder->id)
            ->orderBy('name')
            ->get();

        $crumbs = [];
        foreach (FolderAncestorChain::ancestors($folder) as $ancestor) {
            $crumbs[] = $ancestor;
        }
        $crumbs[] = ['id' => $folder->id, 'name' => $folder->name];

        return [
            'owner_id' => $folder->user_id,
            'folder' => $folder,
            'breadcrumb' => $crumbs,
            'folders' => $this->folders->children($folder->user_id, $folder->id, $folder->workspace_id),
            'files' => $files,
        ];
    }

    public function setVisibility(User $actor, string $folderId, string $visibility, ?string $userId = null, ?string $workspaceId = null): Folder
    {
        $folder = $this->resolveFolderForMutate($actor, $folderId, $userId, $workspaceId);
        $from = (string) $folder->visibility;
        $value = $this->visibility->normalize($visibility);

        $folder->forceFill(['visibility' => $value->value])->save();
        $folder = $folder->refresh();

        if ($folder->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($folder->workspace_id);
            $this->activity->folderVisibilityChanged($ws, $actor, $folder, $from, $value->value);
        }

        $this->realtime->folderListing('visibility', $folder, $actor);

        return $folder;
    }

    public function create(User $actor, string $name, ?string $parentId, ?string $userId = null, ?string $workspaceId = null): Folder
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);
        $wsId = $workspace?->id;

        $folder = $this->folders->create($ownerId, $parentId, $name, $wsId);

        if ($workspace !== null) {
            $this->activity->folderCreated($workspace, $actor, $folder);
        }

        $this->realtime->folderListing('create', $folder, $actor);

        return $folder;
    }

    public function rename(User $actor, string $folderId, string $name, ?string $userId = null, ?string $workspaceId = null): Folder
    {
        $folder = $this->resolveFolderForMutate($actor, $folderId, $userId, $workspaceId);
        $from = $folder->name;
        $folder = $this->folders->rename($folder, $name);

        if ($folder->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($folder->workspace_id);
            $this->activity->folderRenamed($ws, $actor, $folder, $from, $name);
        }

        $this->realtime->folderListing('rename', $folder, $actor);

        return $folder;
    }

    public function move(User $actor, string $folderId, ?string $parentId, ?string $userId = null, ?string $workspaceId = null): Folder
    {
        $folder = $this->resolveFolderForMutate($actor, $folderId, $userId, $workspaceId);
        $fromParent = $folder->parent_id;
        $folder = $this->folders->move($folder, $parentId);

        if ($folder->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($folder->workspace_id);
            $this->activity->folderMoved($ws, $actor, $folder, $fromParent, $parentId);
        }

        $this->realtime->folderListing('move', $folder, $actor, fromFolderId: $fromParent);

        return $folder;
    }

    public function softDelete(User $actor, string $folderId, ?string $userId = null, ?string $workspaceId = null): void
    {
        $folder = $this->resolveFolderForMutate($actor, $folderId, $userId, $workspaceId);
        $workspace = $folder->workspace_id !== null
            ? $this->workspaceAccess->findOrFail($folder->workspace_id)
            : null;

        $this->folders->softDeleteCascade($folder);

        if ($workspace !== null) {
            $this->activity->folderSoftDeleted($workspace, $actor, $folder);
        }

        $this->realtime->folderListing('soft_delete', $folder, $actor, includeResource: false);
        $this->realtime->quotaChanged((string) $folder->user_id);
    }

    public function bulkSetVisibility(User $actor, array $folderIds, string $visibility, ?string $userId = null, ?string $workspaceId = null): int
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);
        $value = $this->visibility->normalize($visibility);

        $count = Folder::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($folderIds)
            ->update(['visibility' => $value->value]);

        if ($count > 0) {
            $this->realtime->bulkListing(
                'bulk_visibility',
                'folder',
                $folderIds,
                $ownerId,
                $workspace?->id,
                $actor,
            );
        }

        return $count;
    }

    public function bulkSoftDelete(User $actor, array $folderIds, ?string $userId = null, ?string $workspaceId = null): int
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $folders = Folder::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($folderIds)
            ->get();

        foreach ($folders as $folder) {
            $this->folders->softDeleteCascade($folder);
            if ($workspace !== null) {
                $this->activity->folderSoftDeleted($workspace, $actor, $folder);
            }
        }

        $count = $folders->count();
        if ($count > 0) {
            $this->realtime->bulkListing(
                'bulk_soft_delete',
                'folder',
                $folders->pluck('id')->all(),
                $ownerId,
                $workspace?->id,
                $actor,
            );
            $this->realtime->quotaChanged($ownerId);
        }

        return $count;
    }

    public function restore(User $actor, string $folderId, ?string $userId = null, ?string $workspaceId = null): Folder
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $folder = Folder::onlyTrashed()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($folderId)
            ->firstOrFail();

        $folder = $this->folders->restore($folder);

        if ($workspace !== null) {
            $this->activity->folderRestored($workspace, $actor, $folder);
        }

        $this->realtime->folderListing('restore', $folder, $actor);
        $this->realtime->quotaChanged((string) $folder->user_id);

        return $folder;
    }

    public function forceDelete(User $actor, string $folderId, ?string $userId = null, ?string $workspaceId = null): void
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $folder = Folder::withTrashed()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($folderId)
            ->firstOrFail();

        $snapshot = [
            'id' => $folder->id,
            'user_id' => $folder->user_id,
            'workspace_id' => $folder->workspace_id,
            'parent_id' => $folder->parent_id,
        ];

        $this->folders->forceDeleteCascade($folder);

        if ($workspace !== null) {
            $this->activity->folderForceDeleted($workspace, $actor, $folder);
        }

        $this->realtime->listingChanged(
            action: 'force_delete',
            entityType: 'folder',
            entityId: $snapshot['id'],
            ownerUserId: (string) $snapshot['user_id'],
            workspaceId: $snapshot['workspace_id'],
            folderId: $snapshot['parent_id'],
            actorId: $actor->id,
        );
        if ($snapshot['workspace_id'] !== null) {
            $this->realtime->activityAppended((string) $snapshot['workspace_id'], $actor->id);
        }
        $this->realtime->quotaChanged((string) $snapshot['user_id']);
    }

    /**
     * @return Collection<int, Folder>
     */
    public function trash(User $actor, ?string $userId = null, ?string $workspaceId = null): Collection
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: false);

        return $this->folders->trashed($ownerId, $workspace?->id);
    }

    /**
     * @return array{0: string, 1: Workspace|null}
     */
    private function resolveScope(User $actor, ?string $userId, ?string $workspaceId, bool $mutate): array
    {
        if ($workspaceId !== null && $workspaceId !== '') {
            $workspace = $this->workspaceAccess->findOrFail($workspaceId);
            if ($mutate) {
                $this->workspaceAccess->assertCanMutate($workspace, $actor);
            } else {
                $this->workspaceAccess->assertCanView($workspace, $actor);
            }

            return [$this->workspaceAccess->storageOwnerId($workspace), $workspace];
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        if ($mutate) {
            $this->access->assertCanMutateOwner($actor, $owner);
        } else {
            $this->access->assertCanViewOwner($actor, $owner);
        }

        return [$ownerId, null];
    }

    private function resolveFolderForMutate(User $actor, string $folderId, ?string $userId, ?string $workspaceId): Folder
    {
        // Prefer explicit workspace; else load folder and branch on its workspace_id
        if ($workspaceId !== null && $workspaceId !== '') {
            [$ownerId] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

            return $this->folders->findOwnedOrFail($ownerId, $folderId, $workspaceId);
        }

        $folder = Folder::query()->whereKey($folderId)->first();
        if ($folder === null) {
            throw new FileHostingException(__('api/file-hosting.folder_not_found'), 404);
        }

        if ($folder->workspace_id !== null) {
            $this->workspaceAccess->assertResourceMutate($actor, $folder);

            return $folder;
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = $this->folders->findOwnedOrFail($ownerId, $folderId, null);
        $this->access->assertCanMutateOwner($actor, $folder->owner);

        return $folder;
    }
}
