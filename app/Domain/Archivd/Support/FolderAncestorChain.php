<?php

namespace App\Domain\Archivd\Support;

use App\Domain\Archivd\Models\Folder;
use Illuminate\Support\Collection;

class FolderAncestorChain
{
    /**
     * Owner folder index for in-memory parent walks (one query).
     *
     * @return Collection<string, Folder>
     */
    public static function indexForOwner(string $userId): Collection
    {
        return Folder::query()
            ->where('user_id', $userId)
            ->get(['id', 'parent_id', 'name', 'visibility'])
            ->keyBy('id');
    }

    /**
     * Ancestors from root to immediate parent (excludes the folder itself).
     *
     * @return list<array{id: string, name: string}>
     */
    public static function ancestors(Folder $folder): array
    {
        $byId = self::indexForOwner((string) $folder->user_id);
        $chain = [];
        $parentId = $folder->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard < 1000) {
            $parent = $byId->get($parentId);

            if ($parent === null) {
                break;
            }

            $chain[] = [
                'id' => $parent->id,
                'name' => $parent->name,
            ];

            $parentId = $parent->parent_id;
            $guard++;
        }

        return array_reverse($chain);
    }

    /**
     * Breadcrumb including virtual root label + ancestors + optional current folder.
     *
     * @return list<array{id: string|null, name: string}>
     */
    public static function breadcrumb(?Folder $folder, string $rootLabel): array
    {
        $crumbs = [
            ['id' => null, 'name' => $rootLabel],
        ];

        if ($folder === null) {
            return $crumbs;
        }

        foreach (self::ancestors($folder) as $ancestor) {
            $crumbs[] = $ancestor;
        }

        $crumbs[] = [
            'id' => $folder->id,
            'name' => $folder->name,
        ];

        return $crumbs;
    }

    /**
     * True if $candidateId is $folder or any of its descendants (cycle risk on reparent).
     */
    public static function isSelfOrDescendant(Folder $folder, string $candidateId): bool
    {
        if ($folder->id === $candidateId) {
            return true;
        }

        $childrenByParent = Folder::query()
            ->where('user_id', $folder->user_id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $queue = [$folder->id];
        $guard = 0;

        while ($queue !== [] && $guard < 10_000) {
            $current = array_shift($queue);
            $children = $childrenByParent->get($current, collect());

            foreach ($children as $child) {
                $childId = (string) $child->id;
                if ($childId === $candidateId) {
                    return true;
                }
                $queue[] = $childId;
            }

            $guard++;
        }

        return false;
    }
}
