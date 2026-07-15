<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FileService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly ObjectStorage $storage,
    ) {}

    public function findOwnedOrFail(string $ownerId, string $fileId): HostedFile
    {
        $file = HostedFile::query()
            ->where('user_id', $ownerId)
            ->whereKey($fileId)
            ->first();

        if ($file === null) {
            throw new FileHostingException(__('api/file-hosting.file_not_found'), 404);
        }

        return $file;
    }

    public function show(User $actor, string $fileId, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        return $this->findOwnedOrFail($ownerId, $fileId);
    }

    public function rename(User $actor, string $fileId, string $name, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);
        $this->access->assertCanMutateOwner($actor, $file->owner);
        $this->assertUniqueName($ownerId, $file->folder_id, $name, $file->id);
        $file->forceFill(['name' => $name])->save();

        return $file->refresh();
    }

    public function move(User $actor, string $fileId, ?string $folderId, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);
        $this->access->assertCanMutateOwner($actor, $file->owner);

        if ($folderId !== null) {
            $this->folders->findOwnedOrFail($ownerId, $folderId);
        }

        $this->assertUniqueName($ownerId, $folderId, $file->name, $file->id);
        $file->forceFill(['folder_id' => $folderId])->save();

        return $file->refresh();
    }

    public function softDelete(User $actor, string $fileId, ?string $userId = null): void
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);
        $this->access->assertCanMutateOwner($actor, $file->owner);
        $file->delete();
    }

    public function restore(User $actor, string $fileId, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = HostedFile::onlyTrashed()
            ->where('user_id', $ownerId)
            ->whereKey($fileId)
            ->firstOrFail();
        $this->access->assertCanMutateOwner($actor, $file->owner);

        if ($file->folder_id !== null) {
            $folderExists = $this->folders->findOwned($ownerId, $file->folder_id) !== null;
            if (! $folderExists) {
                $file->folder_id = null;
            }
        }

        $file->restore();

        return $file->refresh();
    }

    public function forceDelete(User $actor, string $fileId, ?string $userId = null): void
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = HostedFile::withTrashed()
            ->where('user_id', $ownerId)
            ->whereKey($fileId)
            ->firstOrFail();
        $this->access->assertCanMutateOwner($actor, $file->owner);

        DB::transaction(function () use ($file): void {
            $this->storage->deleteMany([['disk' => $file->disk, 'path' => $file->path]]);
            $file->forceDelete();
        });
    }

    /**
     * @return Collection<int, HostedFile>
     */
    public function trash(User $actor, ?string $userId = null): Collection
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        return HostedFile::onlyTrashed()
            ->where('user_id', $ownerId)
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * @return Collection<int, HostedFile>
     */
    public function search(User $actor, string $query, ?string $userId = null): Collection
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        return HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    public function downloadUrl(User $actor, string $fileId, ?string $userId = null): string
    {
        $file = $this->show($actor, $fileId, $userId);
        $url = $file->temporaryDownloadUrl();

        if ($url === null) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        return $url;
    }

    private function assertUniqueName(
        string $ownerId,
        ?string $folderId,
        string $name,
        ?string $ignoreId = null,
    ): void {
        $query = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new FileHostingException(__('api/file-hosting.file_name_taken'));
        }
    }
}
