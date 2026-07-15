<?php

namespace App\Domain\FileHosting\Services;

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
