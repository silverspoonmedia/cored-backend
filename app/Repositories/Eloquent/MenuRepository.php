<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\Navigation\InvalidMenuParentException;
use App\Exceptions\Navigation\InvalidMenuPositionException;
use App\Exceptions\Navigation\MenuHasChildrenException;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Support\Localization\TranslationResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuRepository implements MenuRepositoryInterface
{
    /**
     * Sibling sort priority by type: headers, then parents, then children.
     */
    private const TYPE_RANK = ['h' => 0, 'p' => 1, 'c' => 2];

    /**
     * Cache key for the serialization-safe navigation snapshot.
     */
    private const CACHE_KEY = 'menus.navigation.snapshot';

    /**
     * Sentinel for grouping root-level menus (parent_id === null).
     */
    private const ROOT = '__root__';

    public function navigationTree(?User $user): array
    {
        $nodes = $this->navigationSnapshot();

        return $this->buildVisibleTree(null, $user, $nodes, $this->indexChildren($nodes));
    }

    public function adminFlatList(?string $loadType): array
    {
        $query = Menu::query()->with(['roles', 'includedUsers', 'excludedUsers']);

        if ($loadType !== null && $loadType !== '') {
            $query->where('type', $loadType);
        }

        $menus = $query->get()->keyBy('id');

        $roots = $menus->whereNull('parent_id')
            ->sort(fn (Menu $a, Menu $b) => $this->compareModels($a, $b))
            ->values();

        $flat = [];
        foreach ($roots as $root) {
            $this->appendDepthFirst($root, $menus, $flat);
        }

        return $flat;
    }

    public function read(string $id): Menu
    {
        return Menu::query()
            ->with(['parent', 'children', 'roles', 'includedUsers', 'excludedUsers'])
            ->findOrFail($id);
    }

    public function createMenu(array $data, ?string $position, ?string $referenceId): Menu
    {
        $menu = DB::transaction(function () use ($data, $position, $referenceId) {
            $parentId = $data['parent_id'] ?? null;
            $type = $data['type'];

            // Insert parked at NULL order; the rebalance below assigns its slot.
            $menu = Menu::query()->create([
                'parent_id' => $parentId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'route' => $data['route'] ?? null,
                'type' => $type,
                'order' => null,
                'is_authenticate' => $data['is_authenticate'] ?? null,
                'is_guest_only' => $data['is_guest_only'] ?? null,
            ]);

            $ids = $this->orderedSiblingIds($parentId, $type);
            $ids = array_values(array_filter($ids, fn (string $id) => $id !== $menu->id));

            if ($position === 'before' || $position === 'after') {
                if ($referenceId === null || $referenceId === '') {
                    throw new InvalidMenuPositionException(__('api/navigation.position.reference_required'));
                }

                $this->assertSameGroup($menu, $referenceId, $parentId, $type);

                $refIndex = array_search($referenceId, $ids, true);
                if ($refIndex === false) {
                    throw new InvalidMenuPositionException(__('api/navigation.position.reference_not_in_group'));
                }

                $offset = $position === 'before' ? $refIndex : $refIndex + 1;
                array_splice($ids, $offset, 0, [$menu->id]);
            } else {
                $ids[] = $menu->id;
            }

            $this->persistOrderSequence($ids);

            return $menu->fresh();
        });

        $this->flushCache();

        return $menu;
    }

    public function updateMenu(Menu $menu, array $data): Menu
    {
        $menu = DB::transaction(function () use ($menu, $data) {
            $oldParent = $menu->parent_id;
            $oldType = $menu->type;

            foreach (['parent_id', 'name', 'description', 'icon', 'route', 'type', 'is_authenticate', 'is_guest_only'] as $key) {
                if (array_key_exists($key, $data)) {
                    $menu->{$key} = $data[$key];
                }
            }

            $menu->save();

            // Moving across groups: compact the old group, append to the new one.
            if ($menu->parent_id !== $oldParent || $menu->type !== $oldType) {
                $this->removeFromSiblingOrder($oldParent, $oldType, $menu->id);

                $ids = array_values(array_filter(
                    $this->orderedSiblingIds($menu->parent_id, $menu->type),
                    fn (string $id) => $id !== $menu->id,
                ));
                $ids[] = $menu->id;

                $this->persistOrderSequence($ids);
            }

            return $menu->fresh();
        });

        $this->flushCache();

        return $menu;
    }

    public function updateMenuPosition(Menu $menu, string $position, string $referenceId): Menu
    {
        $menu = DB::transaction(function () use ($menu, $position, $referenceId) {
            $this->assertSameGroup($menu, $referenceId, $menu->parent_id, $menu->type);

            $ids = $this->orderedSiblingIds($menu->parent_id, $menu->type);
            $ids = array_values(array_filter($ids, fn (string $id) => $id !== $menu->id));

            $refIndex = array_search($referenceId, $ids, true);
            if ($refIndex === false) {
                throw new InvalidMenuPositionException(__('api/navigation.position.reference_not_in_group'));
            }

            if ($position === 'before') {
                array_splice($ids, $refIndex, 0, [$menu->id]);
            } elseif ($position === 'after') {
                array_splice($ids, $refIndex + 1, 0, [$menu->id]);
            } else {
                throw new InvalidMenuPositionException(__('api/navigation.position.invalid_position'));
            }

            $this->persistOrderSequence($ids);

            return $menu->fresh();
        });

        $this->flushCache();

        return $menu;
    }

    public function deleteMenu(Menu $menu, bool $cascade = false): void
    {
        DB::transaction(function () use ($menu, $cascade) {
            $descendantIds = $this->activeDescendantIds($menu->id);

            // Soft delete does not fire the FK cascade, so a branch delete would
            // otherwise orphan its subtree. Require explicit intent to remove it.
            if ($descendantIds !== [] && ! $cascade) {
                throw new MenuHasChildrenException;
            }

            $parentId = $menu->parent_id;
            $type = $menu->type;

            if ($descendantIds !== []) {
                // Park orders at NULL (free the unique slots) then soft-delete.
                Menu::query()->whereIn('id', $descendantIds)->update(['order' => null]);
                Menu::query()->whereIn('id', $descendantIds)->delete();
            }

            // Park the order at NULL before soft-deleting so the freed slot can
            // be reused by an active sibling without violating the unique index.
            $menu->forceFill(['order' => null])->save();
            $menu->delete();

            $this->persistOrderSequence($this->orderedSiblingIds($parentId, $type));
        });

        $this->flushCache();
    }

    public function restoreMenu(Menu $menu): void
    {
        DB::transaction(function () use ($menu) {
            // Restoring under a still-trashed parent would re-orphan the branch
            // (invisible in the tree because the parent is unreachable).
            if ($menu->parent_id !== null && ! Menu::query()->whereKey($menu->parent_id)->exists()) {
                throw new InvalidMenuParentException(__('api/navigation.parent_restore_failed'));
            }

            $ids = $this->trashedSubtreeIds($menu->id);

            if ($ids === []) {
                return;
            }

            // Rows come back with order still NULL, then get appended per group.
            Menu::withTrashed()->whereIn('id', $ids)->restore();

            $groups = Menu::query()
                ->whereIn('id', $ids)
                ->get(['parent_id', 'type'])
                ->map(fn (Menu $m): array => ['parent_id' => $m->parent_id, 'type' => $m->type])
                ->unique(fn (array $group): string => ($group['parent_id'] ?? self::ROOT).'|'.$group['type']);

            foreach ($groups as $group) {
                $this->persistOrderSequence($this->siblingIdsForAppend($group['parent_id'], $group['type']));
            }
        });

        $this->flushCache();
    }

    public function trashedList(?string $loadType): array
    {
        $query = Menu::onlyTrashed()->with(['roles', 'includedUsers', 'excludedUsers']);

        if ($loadType !== null && $loadType !== '') {
            $query->where('type', $loadType);
        }

        return $query->orderByDesc('deleted_at')->get()->all();
    }

    public function syncRoles(Menu $menu, array $idsOrNames): void
    {
        if ($idsOrNames === []) {
            $menu->roles()->sync([]);
            $this->flushCache();

            return;
        }

        $roleIds = Role::query()
            ->where('guard_name', 'api')
            ->where(function (Builder $q) use ($idsOrNames) {
                $q->whereIn('id', $idsOrNames)
                    ->orWhereIn('name', $idsOrNames);
            })
            ->pluck('id')
            ->all();

        $menu->roles()->sync($roleIds);
        $this->flushCache();
    }

    public function syncUserIncludes(Menu $menu, array $userIds): void
    {
        $menu->includedUsers()->sync($userIds);
        $this->flushCache();
    }

    public function syncUserExcludes(Menu $menu, array $userIds): void
    {
        $menu->excludedUsers()->sync($userIds);
        $this->flushCache();
    }

    /**
     * Build (and cache) a serialization-safe snapshot of every active menu: a
     * plain array keyed by id holding only scalars and the RBAC associations as
     * id/name arrays. Caching scalars — never hydrated Eloquent models — keeps
     * the value safe to round-trip through any cache store (database, redis,
     * file); caching models produces `__PHP_Incomplete_Class` on deserialize.
     *
     * @return array<string, array<string, mixed>>
     */
    private function navigationSnapshot(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Menu::query()
                ->with(['roles', 'includedUsers', 'excludedUsers'])
                ->get()
                ->map(fn (Menu $menu): array => [
                    'id' => $menu->id,
                    'parent_id' => $menu->parent_id,
                    'name_translations' => $menu->getTranslations('name'),
                    'description_translations' => $menu->getTranslations('description'),
                    'icon' => $menu->icon,
                    'route' => $menu->route,
                    'type' => $menu->type,
                    'order' => $menu->order,
                    'is_authenticate' => $menu->is_authenticate,
                    'is_guest_only' => $menu->is_guest_only,
                    'role_names' => $menu->roles->where('guard_name', 'api')->pluck('name')->values()->all(),
                    'include_user_ids' => $menu->includedUsers->pluck('id')->values()->all(),
                    'exclude_user_ids' => $menu->excludedUsers->pluck('id')->values()->all(),
                ])
                ->keyBy('id')
                ->all();
        });
    }

    public function flushNavigationCache(): void
    {
        $this->flushCache();
    }

    private function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Active descendant ids of the given menu (depth-first), trashed excluded.
     *
     * @return list<string>
     */
    private function activeDescendantIds(string $menuId): array
    {
        $childrenByParent = [];
        foreach (Menu::query()->get(['id', 'parent_id']) as $menu) {
            $childrenByParent[$menu->parent_id][] = $menu->id;
        }

        $descendants = [];
        $stack = [$menuId];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $descendants[] = $childId;
                $stack[] = $childId;
            }
        }

        return $descendants;
    }

    /**
     * The menu plus its trashed descendants (depth-first). Empty when the menu
     * itself is not trashed.
     *
     * @return list<string>
     */
    private function trashedSubtreeIds(string $menuId): array
    {
        if (! Menu::onlyTrashed()->whereKey($menuId)->exists()) {
            return [];
        }

        $childrenByParent = [];
        foreach (Menu::onlyTrashed()->get(['id', 'parent_id']) as $menu) {
            $childrenByParent[$menu->parent_id][] = $menu->id;
        }

        $ids = [$menuId];
        $stack = [$menuId];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Active sibling ids ordered for an append: rows that already hold an order
     * keep their relative position, freshly-restored rows (order NULL) trail
     * behind in creation order. Used to re-sequence a group after a restore.
     *
     * @return list<string>
     */
    private function siblingIdsForAppend(?string $parentId, string $type): array
    {
        $siblings = Menu::query()
            ->byParent($parentId)
            ->where('type', $type)
            ->orderBy('created_at')
            ->get(['id', 'order']);

        $withOrder = $siblings->filter(fn (Menu $m): bool => $m->order !== null)->sortBy('order')->values();
        $withoutOrder = $siblings->filter(fn (Menu $m): bool => $m->order === null)->values();

        return $withOrder->concat($withoutOrder)->pluck('id')->all();
    }

    /**
     * Group menu ids by parent (root menus under the ROOT sentinel).
     *
     * @param  array<string, array<string, mixed>>  $nodes
     * @return array<string, list<string>>
     */
    private function indexChildren(array $nodes): array
    {
        $index = [];
        foreach ($nodes as $node) {
            $index[$node['parent_id'] ?? self::ROOT][] = $node['id'];
        }

        return $index;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, list<string>>  $childrenByParent
     * @return list<array<string, mixed>>
     */
    private function buildVisibleTree(?string $parentId, ?User $user, array $nodes, array $childrenByParent): array
    {
        $childIds = $childrenByParent[$parentId ?? self::ROOT] ?? [];

        $children = array_map(fn (string $id) => $nodes[$id], $childIds);
        usort($children, fn (array $a, array $b) => $this->compareNodes($a, $b));

        $out = [];
        foreach ($children as $child) {
            if (! $this->chainVisible($child, $user, $nodes)) {
                continue;
            }

            $nested = $this->buildVisibleTree($child['id'], $user, $nodes, $childrenByParent);

            // Hide empty headers/parents: a branch with no reachable leaf is noise.
            if (in_array($child['type'], ['h', 'p'], true) && $nested === []) {
                continue;
            }

            $out[] = [
                'id' => $child['id'],
                'name' => TranslationResolver::resolve($child['name_translations'] ?? []),
                'description' => TranslationResolver::resolve($child['description_translations'] ?? []),
                'icon' => $child['icon'],
                'route' => $child['route'],
                'type' => $child['type'],
                'order' => $child['order'],
                'children' => $nested,
            ];
        }

        return $out;
    }

    /**
     * A node is visible only if it and every ancestor pass the leaf rules.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $nodes
     */
    private function chainVisible(array $node, ?User $user, array $nodes): bool
    {
        $current = $node;
        while ($current !== null) {
            if (! $this->nodeVisibleLeafRules($current, $user)) {
                return false;
            }

            $parentId = $current['parent_id'];
            $current = $parentId !== null ? ($nodes[$parentId] ?? null) : null;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nodeVisibleLeafRules(array $node, ?User $user): bool
    {
        if ($user && in_array($user->id, $node['exclude_user_ids'], true)) {
            return false;
        }

        if ($node['include_user_ids'] !== []) {
            if (! $user || ! in_array($user->id, $node['include_user_ids'], true)) {
                return false;
            }
        }

        if ($node['is_guest_only'] && $user) {
            return false;
        }

        if ($node['is_authenticate'] && ! $user) {
            return false;
        }

        if ($node['role_names'] === []) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole($node['role_names']);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareNodes(array $a, array $b): int
    {
        $rankA = self::TYPE_RANK[$a['type']] ?? 99;
        $rankB = self::TYPE_RANK[$b['type']] ?? 99;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    }

    /**
     * @param  Collection<string, Menu>  $menusById
     * @param  list<Menu>  $sink
     */
    private function appendDepthFirst(Menu $node, Collection $menusById, array &$sink): void
    {
        $sink[] = $node;

        $children = $menusById->where('parent_id', $node->id)
            ->sort(fn (Menu $a, Menu $b) => $this->compareModels($a, $b))
            ->values();

        foreach ($children as $child) {
            $this->appendDepthFirst($child, $menusById, $sink);
        }
    }

    private function compareModels(Menu $a, Menu $b): int
    {
        $rankA = self::TYPE_RANK[$a->type] ?? 99;
        $rankB = self::TYPE_RANK[$b->type] ?? 99;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        return ($a->order ?? 0) <=> ($b->order ?? 0);
    }

    /**
     * @return list<string>
     */
    private function orderedSiblingIds(?string $parentId, string $type): array
    {
        return Menu::query()
            ->byParent($parentId)
            ->where('type', $type)
            ->orderBy('order')
            ->pluck('id')
            ->all();
    }

    /**
     * Rewrite the order column to a contiguous 1..N sequence.
     *
     * Done in two phases to never collide with the unique (parent_id, type,
     * order) index: first park every affected row at NULL, then assign the
     * final positions. Without the NULL phase, a straight rewrite (e.g. setting
     * row A from 1 to 2 while row B still holds 2) would violate the constraint.
     *
     * @param  list<string>  $orderedIds
     */
    private function persistOrderSequence(array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        Menu::query()->whereIn('id', $orderedIds)->update(['order' => null]);

        $position = 1;
        foreach ($orderedIds as $id) {
            Menu::query()->whereKey($id)->update(['order' => $position]);
            $position++;
        }
    }

    private function removeFromSiblingOrder(?string $parentId, string $type, string $menuId): void
    {
        $ids = array_values(array_filter(
            $this->orderedSiblingIds($parentId, $type),
            fn (string $id) => $id !== $menuId,
        ));

        $this->persistOrderSequence($ids);
    }

    private function assertSameGroup(Menu $menu, string $referenceId, ?string $parentId, string $type): void
    {
        if ($menu->id === $referenceId) {
            throw new InvalidMenuPositionException(__('api/navigation.position.reference_same_menu'));
        }

        $ref = Menu::query()->findOrFail($referenceId);
        if ($ref->parent_id !== $parentId || $ref->type !== $type) {
            throw new InvalidMenuPositionException(__('api/navigation.position.reference_wrong_group'));
        }
    }
}
