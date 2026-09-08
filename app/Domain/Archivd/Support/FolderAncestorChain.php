<?php

namespace App\Domain\Archivd\Support;

use App\Domain\Archivd\Models\Folder;

class FolderAncestorChain
{
    /**
     * Ancestors from root to immediate parent (excludes the folder itself).
     *
     * @return list<array{id: string, name: string}>
     */
    public static function ancestors(Folder $folder): array
    {
        $chain = [];
        $parentId = $folder->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard < 1000) {
            $parent = Folder::query()
                ->whereKey($parentId)
                ->where('user_id', $folder->user_id)
                ->first(['id', 'parent_id', 'name']);

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

        $queue = [$folder->id];
        $guard = 0;

        while ($queue !== [] && $guard < 10_000) {
            $current = array_shift($queue);
            $children = Folder::query()
                ->where('user_id', $folder->user_id)
                ->where('parent_id', $current)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
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
