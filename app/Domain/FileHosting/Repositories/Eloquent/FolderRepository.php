<?php

namespace App\Domain\FileHosting\Repositories\Eloquent;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FolderAncestorChain;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\FileHosting\Support\UniqueDisplayNameAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly UniqueDisplayNameAllocator $displayNames = new UniqueDisplayNameAllocator,
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
    ) {}

    public function findOwned(string $ownerId, string $folderId, ?string $workspaceId = null): ?Folder
    {
        return $this->scopedQuery($ownerId, $workspaceId)
            ->whereKey($folderId)
            ->first();
    }

    public function findOwnedOrFail(string $ownerId, string $folderId, ?string $workspaceId = null): Folder
    {
        $folder = $this->findOwned($ownerId, $folderId, $workspaceId);

        if ($folder === null) {
            throw new FileHostingException(__('api/file-hosting.folder_not_found'), 404);
        }

        return $folder;
    }

    public function children(string $ownerId, ?string $parentId, ?string $workspaceId = null): Collection
    {
        return $this->scopedQuery($ownerId, $workspaceId)
            ->where('parent_id', $parentId)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function create(string $ownerId, ?string $parentId, string $name, ?string $workspaceId = null): Folder
    {
        return DB::transaction(function () use ($ownerId, $parentId, $name, $workspaceId): Folder {
            if ($parentId !== null) {
                $parent = $this->findOwnedOrFail($ownerId, $parentId, $workspaceId);
                // Parent defines workspace scope if not explicitly passed
                $workspaceId = $parent->workspace_id;
            }

            $this->assertUniqueSiblingName($ownerId, $parentId, $name, null, $workspaceId);

            $order = (int) $this->scopedQuery($ownerId, $workspaceId)
                ->where('parent_id', $parentId)
                ->max('order');

            return Folder::query()->create([
                'user_id' => $ownerId,
                'workspace_id' => $workspaceId,
                'parent_id' => $parentId,
                'name' => $name,
                'order' => $order + 1,
            ]);
        });
    }

    public function rename(Folder $folder, string $name): Folder
    {
        return DB::transaction(function () use ($folder, $name): Folder {
            $this->assertUniqueSiblingName(
                $folder->user_id,
                $folder->parent_id,
                $name,
                $folder->id,
                $folder->workspace_id,
            );
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
                $parent = $this->findOwnedOrFail($folder->user_id, $newParentId, $folder->workspace_id);

                if (FolderAncestorChain::isSelfOrDescendant($folder, $parent->id)) {
                    throw new FileHostingException(__('api/file-hosting.folder_cycle'));
                }
            }

            // Match file upload/move: auto "Name (N)" on sibling collision — never reject move.
            $displayName = $this->displayNames->allocateFolder(
                $folder->user_id,
                $newParentId,
                $folder->name,
                $folder->workspace_id,
                $folder->id,
            );

            $folder->forceFill(['order' => null])->save();

            $order = (int) $this->scopedQuery($folder->user_id, $folder->workspace_id)
                ->where('parent_id', $newParentId)
                ->max('order');

            $folder->forceFill([
                'parent_id' => $newParentId,
                'name' => $displayName,
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
                $parentExists = $this->scopedQuery($folder->user_id, $folder->workspace_id)
                    ->whereKey($folder->parent_id)
                    ->exists();

                if (! $parentExists) {
                    $folder->parent_id = null;
                }
            }

            $folder->restore();
            $this->assertUniqueSiblingName(
                $folder->user_id,
                $folder->parent_id,
                $folder->name,
                $folder->id,
                $folder->workspace_id,
            );

            HostedFile::onlyTrashed()
                ->where('user_id', $folder->user_id)
                ->where('workspace_id', $folder->workspace_id)
                ->where('folder_id', $folder->id)
                ->restore();

            // Bulk query restore above does NOT fire Eloquent `restored` event,
            // so analytics counters are updated explicitly here.
            $restoredActive = HostedFile::query()
                ->where('user_id', $folder->user_id)
                ->where('workspace_id', $folder->workspace_id)
                ->where('folder_id', $folder->id)
                ->get();

            $analytics = app(\App\Support\Product\Services\ProductAnalyticsService::class);
            $analytics->increment('FileHosting', $folder->user_id, 'file_count', $restoredActive->count());
            $analytics->increment('FileHosting', $folder->user_id, 'storage_used_bytes', (int) $restoredActive->sum('size'));

            Folder::onlyTrashed()
                ->where('user_id', $folder->user_id)
                ->where('workspace_id', $folder->workspace_id)
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

    public function trashed(string $ownerId, ?string $workspaceId = null): Collection
    {
        return $this->scopedQuery($ownerId, $workspaceId, onlyTrashed: true)
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * @return Builder<Folder>
     */
    private function scopedQuery(string $ownerId, ?string $workspaceId, bool $onlyTrashed = false): Builder
    {
        $query = $onlyTrashed ? Folder::onlyTrashed() : Folder::query();

        $query->where('user_id', $ownerId);

        if ($workspaceId === null) {
            $query->whereNull('workspace_id');
        } else {
            $query->where('workspace_id', $workspaceId);
        }

        return $query;
    }

    private function softDeleteTree(Folder $folder): void
    {
        Folder::query()
            ->where('user_id', $folder->user_id)
            ->where('workspace_id', $folder->workspace_id)
            ->where('parent_id', $folder->id)
            ->get()
            ->each(fn (Folder $child) => $this->softDeleteTree($child));

        HostedFile::query()
            ->where('user_id', $folder->user_id)
            ->where('workspace_id', $folder->workspace_id)
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
            ->where('workspace_id', $folder->workspace_id)
            ->where('parent_id', $folder->id)
            ->get()
            ->each(fn (Folder $child) => $this->forceDeleteTree($child));

        $files = HostedFile::withTrashed()
            ->where('user_id', $folder->user_id)
            ->where('workspace_id', $folder->workspace_id)
            ->where('folder_id', $folder->id)
            ->get();

        $this->storage->deleteMany($files->map(fn (HostedFile $f) => [
            'disk' => $f->disk,
            'path' => $this->objectKeys->objectKey($f->user_id, $f->path),
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
        ?string $workspaceId = null,
    ): void {
        $query = $this->scopedQuery($ownerId, $workspaceId)
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
