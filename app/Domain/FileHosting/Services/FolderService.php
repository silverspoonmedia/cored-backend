<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FolderAncestorChain;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly VisibilityService $visibility,
    ) {}

    /**
     * @return array{
     *     owner_id: string,
     *     folder: Folder|null,
     *     breadcrumb: list<array{id: string|null, name: string}>,
     *     folders: Collection<int, Folder>,
     *     files: Collection<int, HostedFile>
     * }
     */
    public function browse(User $actor, ?string $folderId, ?string $userId = null): array
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = null;

        if ($folderId !== null) {
            $folder = $this->folders->findOwnedOrFail($ownerId, $folderId);
        }

        $files = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('folder_id', $folderId)
            ->orderBy('name')
            ->get();

        return [
            'owner_id' => $ownerId,
            'folder' => $folder,
            'breadcrumb' => FolderAncestorChain::breadcrumb(
                $folder,
                __('api/file-hosting.root_label'),
            ),
            'folders' => $this->folders->children($ownerId, $folderId),
            'files' => $files,
        ];
    }

    /**
     * Guest browse of a link-shared folder (and descendants via inherit).
     *
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
            'folders' => $this->folders->children($folder->user_id, $folder->id),
            'files' => $files,
        ];
    }

    public function setVisibility(User $actor, string $folderId, string $visibility, ?string $userId = null): Folder
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = $this->folders->findOwnedOrFail($ownerId, $folderId);
        $this->access->assertCanMutateOwner($actor, $folder->owner);
        $value = $this->visibility->normalize($visibility);

        $folder->forceFill(['visibility' => $value->value])->save();

        return $folder->refresh();
    }

    public function create(User $actor, string $name, ?string $parentId, ?string $userId = null): Folder
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $this->access->assertCanMutateOwner($actor, $owner);

        return $this->folders->create($ownerId, $parentId, $name);
    }

    public function rename(User $actor, string $folderId, string $name, ?string $userId = null): Folder
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = $this->folders->findOwnedOrFail($ownerId, $folderId);
        $this->access->assertCanMutateOwner($actor, $folder->owner);

        return $this->folders->rename($folder, $name);
    }

    public function move(User $actor, string $folderId, ?string $parentId, ?string $userId = null): Folder
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = $this->folders->findOwnedOrFail($ownerId, $folderId);
        $this->access->assertCanMutateOwner($actor, $folder->owner);

        return $this->folders->move($folder, $parentId);
    }

    public function softDelete(User $actor, string $folderId, ?string $userId = null): void
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = $this->folders->findOwnedOrFail($ownerId, $folderId);
        $this->access->assertCanMutateOwner($actor, $folder->owner);
        $this->folders->softDeleteCascade($folder);
    }

    public function bulkSetVisibility(User $actor, array $folderIds, string $visibility, ?string $userId = null): int
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $value = $this->visibility->normalize($visibility);

        return Folder::query()
            ->where('user_id', $ownerId)
            ->whereKey($folderIds)
            ->update(['visibility' => $value->value]);
    }

    public function bulkSoftDelete(User $actor, array $folderIds, ?string $userId = null): int
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        $folders = Folder::query()
            ->where('user_id', $ownerId)
            ->whereKey($folderIds)
            ->get();

        foreach ($folders as $folder) {
            $this->folders->softDeleteCascade($folder);
        }

        return $folders->count();
    }

    public function restore(User $actor, string $folderId, ?string $userId = null): Folder
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = Folder::onlyTrashed()
            ->where('user_id', $ownerId)
            ->whereKey($folderId)
            ->firstOrFail();
        $this->access->assertCanMutateOwner($actor, $folder->owner);

        return $this->folders->restore($folder);
    }

    public function forceDelete(User $actor, string $folderId, ?string $userId = null): void
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $folder = Folder::withTrashed()
            ->where('user_id', $ownerId)
            ->whereKey($folderId)
            ->firstOrFail();
        $this->access->assertCanMutateOwner($actor, $folder->owner);
        $this->folders->forceDeleteCascade($folder);
    }

    /**
     * @return Collection<int, Folder>
     */
    public function trash(User $actor, ?string $userId = null): Collection
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        return $this->folders->trashed($ownerId);
    }
}
