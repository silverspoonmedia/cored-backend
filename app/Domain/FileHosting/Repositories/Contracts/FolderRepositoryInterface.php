<?php

namespace App\Domain\FileHosting\Repositories\Contracts;

use App\Domain\FileHosting\Models\Folder;
use Illuminate\Database\Eloquent\Collection;

interface FolderRepositoryInterface
{
    public function findOwned(string $ownerId, string $folderId): ?Folder;

    public function findOwnedOrFail(string $ownerId, string $folderId): Folder;

    /**
     * @return Collection<int, Folder>
     */
    public function children(string $ownerId, ?string $parentId): Collection;

    public function create(string $ownerId, ?string $parentId, string $name): Folder;

    public function rename(Folder $folder, string $name): Folder;

    public function move(Folder $folder, ?string $newParentId): Folder;

    public function softDeleteCascade(Folder $folder): void;

    public function restore(Folder $folder): Folder;

    public function forceDeleteCascade(Folder $folder): void;

    /**
     * @return Collection<int, Folder>
     */
    public function trashed(string $ownerId): Collection;
}
