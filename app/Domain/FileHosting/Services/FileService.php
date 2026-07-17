<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\FileHosting\Support\ObjectStorage;
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
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
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

    public function findOrFail(string $fileId): HostedFile
    {
        $file = HostedFile::query()->whereKey($fileId)->first();

        if ($file === null) {
            throw new FileHostingException(__('api/file-hosting.file_not_found'), 404);
        }

        return $file;
    }

    public function show(User $actor, string $fileId, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);

        return $this->recordView($file);
    }

    /**
     * Guest public show — link access only.
     */
    public function publicShow(string $fileId): HostedFile
    {
        $file = $this->findOrFail($fileId);
        $this->visibility->assertGuestCanReadFile($file);

        return $this->recordView($file);
    }

    public function setVisibility(User $actor, string $fileId, string $visibility, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);
        $this->access->assertCanMutateOwner($actor, $file->owner);
        $value = $this->visibility->normalize($visibility);

        $file->forceFill(['visibility' => $value->value])->save();

        return $file->refresh();
    }

    public function rename(User $actor, string $fileId, string $name, ?string $userId = null): HostedFile
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);
        $this->access->assertCanMutateOwner($actor, $file->owner);
        $this->assertUniqueName($ownerId, $file->folder_id, $name, $file->id);

        // Display name changed — invalidate disposition-bound signed URL.
        $file->forceFill([
            'name' => $name,
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();

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
        $file->forceFill([
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();
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
            $this->storage->deleteMany([[
                'disk' => $file->disk,
                'path' => $this->objectKeys->objectKey($file->user_id, $file->path),
            ]]);
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
            ->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($term)])
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function download(User $actor, string $fileId, ?string $userId = null): array
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $file = $this->findOwnedOrFail($ownerId, $fileId);

        return $this->issueDownload($file);
    }

    /**
     * Guest public download — signed URL only (caller redirects 302).
     *
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

        return $file->refresh();
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

        return [
            'url' => $resolved['url'],
            'expires_at' => $resolved['expires_at']->toIso8601String(),
        ];
    }

    public function bulkSetVisibility(User $actor, array $fileIds, string $visibility, ?string $userId = null): int
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $value = $this->visibility->normalize($visibility);

        return HostedFile::query()
            ->where('user_id', $ownerId)
            ->whereKey($fileIds)
            ->update(['visibility' => $value->value]);
    }

    public function bulkSoftDelete(User $actor, array $fileIds, ?string $userId = null): int
    {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);

        $files = HostedFile::query()
            ->where('user_id', $ownerId)
            ->whereKey($fileIds)
            ->get();

        foreach ($files as $file) {
            $file->forceFill([
                'signed_download_url' => null,
                'signed_download_expires_at' => null,
            ])->save();
            $file->delete();
        }

        return $files->count();
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
