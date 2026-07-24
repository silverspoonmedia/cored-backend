<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FileHostingRealtime;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\FileHosting\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FileService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly ObjectStorage $storage,
        private readonly SignedDownloadService $signedDownloads,
        private readonly VisibilityService $visibility,
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceActivityRecorder $activity,
        private readonly FileHostingRealtime $realtime,
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
    ) {}

    public function findOwnedOrFail(string $ownerId, string $fileId, ?string $workspaceId = null): HostedFile
    {
        $query = HostedFile::query()
            ->where('user_id', $ownerId)
            ->whereKey($fileId);

        if ($workspaceId === null) {
            $query->whereNull('workspace_id');
        } else {
            $query->where('workspace_id', $workspaceId);
        }

        $file = $query->first();

        if ($file === null) {
            throw new FileHostingException(__('api/file-hosting.file_not_found'), 404);
        }

        return $file;
    }

    public function findOrFail(string $fileId): HostedFile
    {
        $file = HostedFile::query()->whereKey($fileId)->first();

        if ($file === null) {
            throw new FileHostingException(__('api/file-hosting.file_not_found'), 404);
        }

        return $file;
    }

    public function show(User $actor, string $fileId, ?string $userId = null, ?string $workspaceId = null): HostedFile
    {
        $file = $this->resolveFileForView($actor, $fileId, $userId, $workspaceId);

        return $this->recordView($file);
    }

    public function publicShow(string $fileId): HostedFile
    {
        $file = $this->findOrFail($fileId);
        $this->visibility->assertGuestCanReadFile($file);

        return $this->recordView($file);
    }

    public function setVisibility(User $actor, string $fileId, string $visibility, ?string $userId = null, ?string $workspaceId = null): HostedFile
    {
        $file = $this->resolveFileForMutate($actor, $fileId, $userId, $workspaceId);
        $from = (string) $file->visibility;
        $value = $this->visibility->normalize($visibility);
        $this->visibility->assertVisibilityChangeAllowed($value, (string) $file->user_id);

        // Always drop cached S3 URL so private revoke cannot reuse a still-valid temp link.
        $file->forceFill([
            'visibility' => $value->value,
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();
        $file = $file->refresh();

        if ($file->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($file->workspace_id);
            $this->activity->fileVisibilityChanged($ws, $actor, $file, $from, $value->value);
        }

        $this->realtime->fileListing('visibility', $file, $actor);

        return $file;
    }

    public function rename(User $actor, string $fileId, string $name, ?string $userId = null, ?string $workspaceId = null): HostedFile
    {
        $file = $this->resolveFileForMutate($actor, $fileId, $userId, $workspaceId);
        $from = $file->name;
        $this->assertUniqueName($file->user_id, $file->folder_id, $name, $file->id, $file->workspace_id);

        $file->forceFill([
            'name' => $name,
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();
        $file = $file->refresh();

        if ($file->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($file->workspace_id);
            $this->activity->fileRenamed($ws, $actor, $file, $from, $name);
        }

        $this->realtime->fileListing('rename', $file, $actor);

        return $file;
    }

    public function move(User $actor, string $fileId, ?string $folderId, ?string $userId = null, ?string $workspaceId = null): HostedFile
    {
        $file = $this->resolveFileForMutate($actor, $fileId, $userId, $workspaceId);
        $fromFolder = $file->folder_id;

        if ($folderId !== null) {
            $this->folders->findOwnedOrFail($file->user_id, $folderId, $file->workspace_id);
        }

        $this->assertUniqueName($file->user_id, $folderId, $file->name, $file->id, $file->workspace_id);
        $file->forceFill(['folder_id' => $folderId])->save();
        $file = $file->refresh();

        if ($file->workspace_id !== null) {
            $ws = $this->workspaceAccess->findOrFail($file->workspace_id);
            $this->activity->fileMoved($ws, $actor, $file, $fromFolder, $folderId);
        }

        $this->realtime->fileListing('move', $file, $actor, fromFolderId: $fromFolder);

        return $file;
    }

    public function softDelete(User $actor, string $fileId, ?string $userId = null, ?string $workspaceId = null): void
    {
        $file = $this->resolveFileForMutate($actor, $fileId, $userId, $workspaceId);
        $workspace = $file->workspace_id !== null
            ? $this->workspaceAccess->findOrFail($file->workspace_id)
            : null;

        $file->forceFill([
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();
        $file->delete();

        if ($workspace !== null) {
            $this->activity->fileSoftDeleted($workspace, $actor, $file);
        }

        $this->realtime->fileListing('soft_delete', $file, $actor, includeResource: false);
        $this->realtime->quotaChanged((string) $file->user_id);
    }

    public function restore(User $actor, string $fileId, ?string $userId = null, ?string $workspaceId = null): HostedFile
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $file = HostedFile::onlyTrashed()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($fileId)
            ->firstOrFail();

        if ($file->folder_id !== null) {
            $folderExists = $this->folders->findOwned($ownerId, $file->folder_id, $workspace?->id) !== null;
            if (! $folderExists) {
                $file->folder_id = null;
            }
        }

        $file->restore();
        $file = $file->refresh();

        if ($workspace !== null) {
            $this->activity->fileRestored($workspace, $actor, $file);
        }

        $this->realtime->fileListing('restore', $file, $actor);
        $this->realtime->quotaChanged((string) $file->user_id);

        return $file;
    }

    public function forceDelete(User $actor, string $fileId, ?string $userId = null, ?string $workspaceId = null): void
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $file = HostedFile::withTrashed()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($fileId)
            ->firstOrFail();

        $snapshot = [
            'id' => $file->id,
            'user_id' => $file->user_id,
            'workspace_id' => $file->workspace_id,
            'folder_id' => $file->folder_id,
        ];

        DB::transaction(function () use ($file): void {
            $this->storage->deleteMany([[
                'disk' => $file->disk,
                'path' => $this->objectKeys->objectKey($file->user_id, $file->path),
            ]]);
            $file->forceDelete();
        });

        if ($workspace !== null) {
            $this->activity->fileForceDeleted($workspace, $actor, $file);
        }

        $this->realtime->listingChanged(
            action: 'force_delete',
            entityType: 'file',
            entityId: $snapshot['id'],
            ownerUserId: (string) $snapshot['user_id'],
            workspaceId: $snapshot['workspace_id'],
            folderId: $snapshot['folder_id'],
            actorId: $actor->id,
        );
        if ($snapshot['workspace_id'] !== null) {
            $this->realtime->activityAppended((string) $snapshot['workspace_id'], $actor->id);
        }
        $this->realtime->quotaChanged((string) $snapshot['user_id']);
    }

    /**
     * @return Collection<int, HostedFile>
     */
    public function trash(User $actor, ?string $userId = null, ?string $workspaceId = null): Collection
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: false);

        return HostedFile::onlyTrashed()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * @return Collection<int, HostedFile>
     */
    public function search(User $actor, string $query, ?string $userId = null, ?string $workspaceId = null): Collection
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: false);
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        return HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($term)])
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function download(User $actor, string $fileId, ?string $userId = null, ?string $workspaceId = null): array
    {
        $file = $this->resolveFileForView($actor, $fileId, $userId, $workspaceId);

        return $this->issueDownload($file);
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function publicDownload(string $fileId): array
    {
        $file = $this->findOrFail($fileId);
        $this->visibility->assertGuestCanReadFile($file);

        return $this->issueDownload($file);
    }

    private function recordView(HostedFile $file): HostedFile
    {
        $file->forceFill([
            'view_count' => (int) $file->view_count + 1,
            'last_viewed_at' => Carbon::now(),
        ])->save();
        $file = $file->refresh();
        $this->realtime->fileStats($file);

        return $file;
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    private function issueDownload(HostedFile $file): array
    {
        $resolved = $this->signedDownloads->resolve($file);

        $file->forceFill([
            'download_count' => (int) $file->download_count + 1,
            'last_downloaded_at' => Carbon::now(),
        ])->save();
        $file->refresh();
        $this->realtime->fileStats($file);

        return [
            'url' => $resolved['url'],
            'expires_at' => $resolved['expires_at']->toIso8601String(),
        ];
    }

    public function bulkSetVisibility(User $actor, array $fileIds, string $visibility, ?string $userId = null, ?string $workspaceId = null): int
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);
        $value = $this->visibility->normalize($visibility);
        $this->visibility->assertVisibilityChangeAllowed($value, $ownerId);

        $count = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($fileIds)
            ->update(['visibility' => $value->value]);

        if ($count > 0) {
            $this->realtime->bulkListing(
                'bulk_visibility',
                'file',
                $fileIds,
                $ownerId,
                $workspace?->id,
                $actor,
            );
        }

        return $count;
    }

    public function bulkSoftDelete(User $actor, array $fileIds, ?string $userId = null, ?string $workspaceId = null): int
    {
        [$ownerId, $workspace] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

        $files = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspace?->id)
            ->whereKey($fileIds)
            ->get();

        foreach ($files as $file) {
            $file->forceFill([
                'signed_download_url' => null,
                'signed_download_expires_at' => null,
            ])->save();
            $file->delete();
            if ($workspace !== null) {
                $this->activity->fileSoftDeleted($workspace, $actor, $file);
            }
        }

        $count = $files->count();
        if ($count > 0) {
            $this->realtime->bulkListing(
                'bulk_soft_delete',
                'file',
                $files->pluck('id')->all(),
                $ownerId,
                $workspace?->id,
                $actor,
            );
            $this->realtime->quotaChanged($ownerId);
        }

        return $count;
    }

    private function assertUniqueName(
        string $ownerId,
        ?string $folderId,
        string $name,
        ?string $ignoreId = null,
        ?string $workspaceId = null,
    ): void {
        $query = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new FileHostingException(__('api/file-hosting.file_name_taken'));
        }
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

    private function resolveFileForView(User $actor, string $fileId, ?string $userId, ?string $workspaceId): HostedFile
    {
        if ($workspaceId !== null && $workspaceId !== '') {
            [$ownerId] = $this->resolveScope($actor, $userId, $workspaceId, mutate: false);

            return $this->findOwnedOrFail($ownerId, $fileId, $workspaceId);
        }

        $file = $this->findOrFail($fileId);
        if ($file->workspace_id !== null) {
            $this->workspaceAccess->assertResourceView($actor, $file);

            return $file;
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        return $this->findOwnedOrFail($ownerId, $fileId, null);
    }

    private function resolveFileForMutate(User $actor, string $fileId, ?string $userId, ?string $workspaceId): HostedFile
    {
        if ($workspaceId !== null && $workspaceId !== '') {
            [$ownerId] = $this->resolveScope($actor, $userId, $workspaceId, mutate: true);

            return $this->findOwnedOrFail($ownerId, $fileId, $workspaceId);
        }

        $file = $this->findOrFail($fileId);
        if ($file->workspace_id !== null) {
            $this->workspaceAccess->assertResourceMutate($actor, $file);

            return $file;
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId, null);
        $this->access->assertCanMutateOwner($actor, $file->owner);

        return $file;
    }
}
