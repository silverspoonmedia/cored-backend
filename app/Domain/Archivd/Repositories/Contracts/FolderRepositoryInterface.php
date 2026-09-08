<?php

namespace App\Domain\Archivd\Repositories\Contracts;

use App\Domain\Archivd\Models\Folder;
use Illuminate\Database\Eloquent\Collection;

interface FolderRepositoryInterface
{
    public function findOwned(string $ownerId, string $folderId, ?string $workspaceId = null): ?Folder;

    public function findOwnedOrFail(string $ownerId, string $folderId, ?string $workspaceId = null): Folder;

    /**
     * @return Collection<int, Folder>
     */
    public function children(string $ownerId, ?string $parentId, ?string $workspaceId = null): Collection;

    public function create(string $ownerId, ?string $parentId, string $name, ?string $workspaceId = null): Folder;

    public function rename(Folder $folder, string $name): Folder;

    public function move(Folder $folder, ?string $newParentId): Folder;

    public function softDeleteCascade(Folder $folder): void;

    public function restore(Folder $folder): Folder;

    public function forceDeleteCascade(Folder $folder): void;

    /**
     * @return Collection<int, Folder>
     */
    public function trashed(string $ownerId, ?string $workspaceId = null): Collection;
}
