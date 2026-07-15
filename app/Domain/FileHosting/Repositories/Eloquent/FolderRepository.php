<?php

namespace App\Domain\FileHosting\Repositories\Eloquent;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FolderAncestorChain;
use App\Domain\FileHosting\Support\ObjectStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    public function findOwned(string $ownerId, string $folderId): ?Folder
    {
        return Folder::query()
            ->where('user_id', $ownerId)
            ->whereKey($folderId)
            ->first();
    }

    public function findOwnedOrFail(string $ownerId, string $folderId): Folder
    {
        $folder = $this->findOwned($ownerId, $folderId);

        if ($folder === null) {
            throw new FileHostingException(__('api/file-hosting.folder_not_found'), 404);
        }

        return $folder;
    }

    public function children(string $ownerId, ?string $parentId): Collection
    {
        return Folder::query()
            ->where('user_id', $ownerId)
            ->where('parent_id', $parentId)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function create(string $ownerId, ?string $parentId, string $name): Folder
    {
        return DB::transaction(function () use ($ownerId, $parentId, $name): Folder {
            if ($parentId !== null) {
                $this->findOwnedOrFail($ownerId, $parentId);
            }

            $this->assertUniqueSiblingName($ownerId, $parentId, $name);

            $order = (int) Folder::query()
                ->where('user_id', $ownerId)
                ->where('parent_id', $parentId)
                ->max('order');

            return Folder::query()->create([
                'user_id' => $ownerId,
                'parent_id' => $parentId,
                'name' => $name,
                'order' => $order + 1,
            ]);
        });
    }

    public function rename(Folder $folder, string $name): Folder
    {
        return DB::transaction(function () use ($folder, $name): Folder {
            $this->assertUniqueSiblingName($folder->user_id, $folder->parent_id, $name, $folder->id);
            $folder->forceFill(['name' => $name])->save();

            return $folder->refresh();
        });
    }

    public function move(Folder $folder, ?string $newParentId): Folder
    {
        return DB::transaction(function () use ($folder, $newParentId): Folder {
            if ($newParentId === $folder->parent_id) {
                return $folder;
            }

            if ($newParentId !== null) {
                $parent = $this->findOwnedOrFail($folder->user_id, $newParentId);

                if (FolderAncestorChain::isSelfOrDescendant($folder, $parent->id)) {
                    throw new FileHostingException(__('api/file-hosting.folder_cycle'));
                }
            }

            $this->assertUniqueSiblingName($folder->user_id, $newParentId, $folder->name, $folder->id);

            $folder->forceFill(['order' => null])->save();

            $order = (int) Folder::query()
                ->where('user_id', $folder->user_id)
                ->where('parent_id', $newParentId)
                ->max('order');

            $folder->forceFill([
                'parent_id' => $newParentId,
                'order' => $order + 1,
            ])->save();

            return $folder->refresh();
        });
    }

    public function softDeleteCascade(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $this->softDeleteTree($folder);
        });
    }

    public function restore(Folder $folder): Folder
    {
        return DB::transaction(function () use ($folder): Folder {
            if ($folder->parent_id !== null) {
                $parentExists = Folder::query()
                    ->where('user_id', $folder->user_id)
                    ->whereKey($folder->parent_id)
                    ->exists();

                if (! $parentExists) {
                    $folder->parent_id = null;
                }
            }

            $folder->restore();
            $this->assertUniqueSiblingName($folder->user_id, $folder->parent_id, $folder->name, $folder->id);

            HostedFile::onlyTrashed()
                ->where('user_id', $folder->user_id)
                ->where('folder_id', $folder->id)
                ->restore();

            Folder::onlyTrashed()
                ->where('user_id', $folder->user_id)
                ->where('parent_id', $folder->id)
                ->get()
                ->each(fn (Folder $child) => $this->restore($child));

            return $folder->refresh();
        });
    }

    public function forceDeleteCascade(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $this->forceDeleteTree($folder);
        });
    }

    public function trashed(string $ownerId): Collection
    {
        return Folder::onlyTrashed()
            ->where('user_id', $ownerId)
            ->orderByDesc('deleted_at')
            ->get();
    }

    private function softDeleteTree(Folder $folder): void
    {
        Folder::query()
            ->where('user_id', $folder->user_id)
            ->where('parent_id', $folder->id)
            ->get()
            ->each(fn (Folder $child) => $this->softDeleteTree($child));

        HostedFile::query()
            ->where('user_id', $folder->user_id)
            ->where('folder_id', $folder->id)
            ->get()
            ->each(fn (HostedFile $file) => $file->delete());

        $folder->forceFill(['order' => null])->save();
        $folder->delete();
    }

    private function forceDeleteTree(Folder $folder): void
    {
        Folder::withTrashed()
            ->where('user_id', $folder->user_id)
            ->where('parent_id', $folder->id)
            ->get()
            ->each(fn (Folder $child) => $this->forceDeleteTree($child));

        $files = HostedFile::withTrashed()
            ->where('user_id', $folder->user_id)
            ->where('folder_id', $folder->id)
            ->get();

        $this->storage->deleteMany($files->map(fn (HostedFile $f) => [
            'disk' => $f->disk,
            'path' => $f->path,
        ]));

        foreach ($files as $file) {
            $file->forceDelete();
        }

        $folder->forceDelete();
    }

    private function assertUniqueSiblingName(
        string $ownerId,
        ?string $parentId,
        string $name,
        ?string $ignoreId = null,
    ): void {
        $query = Folder::query()
            ->where('user_id', $ownerId)
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new FileHostingException(__('api/file-hosting.folder_name_taken'));
        }
    }
}
