<?php

namespace App\Domain\Identity\Services\Lookup;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use App\Support\Localization\TranslationResolver;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class LookupService
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function users(User $actor, ?string $search, int $page, int $perPage, array $ids = []): array
    {
        if ($ids !== []) {
            return $this->hydrateUsers($actor, $ids);
        }

        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return $this->emptyPage();
        }

        $query = $this->usersVisibleToActorQuery($actorLevel);

        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $query->orderBy('name');

        return $this->paginateUsers($query, $page, $perPage);
    }

    /**
     * Select2 menu lookup for the SPA's Dynamic Menu CRUD.
     *
     * Tree path (no `ids[]`): results are NESTED trees, one node per top-level
     * root menu (headers/parents at the root of a layout). Roots are paginated
     * (lazy load scrolls to the next root group); each root carries its ENTIRE
     * subtree so a group is never split across pages. Each node carries its
     * `level` (0 at root) so the SPA can indent nested children — select2
     * renders only one optgroup level natively.
     *
     * Hydrate path (`ids[]`): flat `{id, text, group, parent_text, level}`
     * rows for pre-selected values — used by the SPA to render the selection
     * without a search.
     *
     * @param  list<string>  $ids
     * @return array{results: list<array<string, mixed>>, pagination: array{more: bool}}
     */
    public function menus(
        ?string $search,
        int $page,
        int $perPage,
        string $layout,
        ?string $excludeId = null,
        array $ids = [],
    ): array {
        if ($ids !== []) {
            return $this->hydrateMenus($ids, $layout, $excludeId);
        }

        return $this->menuTreePage($search, $page, $perPage, $layout, $excludeId);
    }

    /**
     * Paginated root groups, each with its full nested subtree.
     *
     * @return array{results: list<array<string, mixed>>, pagination: array{more: bool}}
     */
    private function menuTreePage(
        ?string $search,
        int $page,
        int $perPage,
        string $layout,
        ?string $excludeId,
    ): array {
        // One query for the whole layout; trees are grouped in memory. Menus
        // per layout are in the hundreds at most — cheaper than per-root
        // subtree queries and keeps sibling ordering consistent.
        $menus = Menu::query()
            ->with('parent')
            ->where('layout', $layout)
            ->orderBy('type')
            ->orderBy('order')
            ->get()
            ->keyBy('id');

        $childrenByParent = [];
        foreach ($menus as $menu) {
            $childrenByParent[$menu->parent_id ?? self::ROOT_SENTINEL][] = $menu;
        }

        $needle = $this->menuSearchNeedle($search);
        $matchIds = $needle !== null ? $this->menuMatchIds($menus, $needle) : null;

        $roots = collect($childrenByParent[self::ROOT_SENTINEL] ?? [])
            ->reject(fn (Menu $menu) => $excludeId !== null && $menu->id === $excludeId)
            ->sort(fn (Menu $a, Menu $b) => $this->compareMenuSiblings($a, $b))
            ->values();

        $offset = ($page - 1) * $perPage;
        $pageRoots = $roots->slice($offset, $perPage + 1);
        $more = $pageRoots->count() > $perPage;
        if ($more) {
            $pageRoots = $pageRoots->take($perPage);
        }

        $results = $pageRoots
            ->map(fn (Menu $root) => $this->menuTreeNode($root, $menus, $childrenByParent, $excludeId, $matchIds))
            ->filter(fn ($node) => $node !== null)
            ->values()
            ->all();

        return [
            'results' => $results,
            'pagination' => ['more' => $more],
        ];
    }

    private const ROOT_SENTINEL = '__lookup_root__';

    /**
     * Build one nested tree node (with its whole subtree) for a root menu.
     * Returns null when a search filter leaves the branch with no matches.
     *
     * @param  Collection<string, Menu>  $menus
     * @param  array<string, list<Menu>>  $childrenByParent
     * @param  array<string, true>|null  $matchIds
     * @return array<string, mixed>|null
     */
    private function menuTreeNode(
        Menu $menu,
        Collection $menus,
        array $childrenByParent,
        ?string $excludeId,
        ?array $matchIds,
        int $level = 0,
    ): ?array {
        $children = collect($childrenByParent[$menu->id] ?? [])
            ->sort(fn (Menu $a, Menu $b) => $this->compareMenuSiblings($a, $b))
            ->values()
            ->map(fn (Menu $child) => $this->menuTreeNode($child, $menus, $childrenByParent, $excludeId, $matchIds, $level + 1))
            ->filter(fn ($node) => $node !== null)
            ->values()
            ->all();

        $selfMatch = $matchIds === null || isset($matchIds[$menu->id]);
        if (! $selfMatch && $children === []) {
            return null;
        }

        return [
            'id' => (string) $menu->id,
            'text' => $this->menuText($menu),
            'level' => $level,
            'children' => $children,
        ];
    }

    private function menuText(Menu $menu): string
    {
        return TranslationResolver::resolve($menu->getTranslations('name')).' · '.$menu->type;
    }

    /**
     * Sibling comparator — identical to the sidebar's MenuRepository ordering
     * (type rank h < p < c, then order) so the dropdown mirrors the tree.
     */
    private function compareMenuSiblings(Menu $a, Menu $b): int
    {
        $rankA = self::TYPE_RANK[$a->type] ?? 99;
        $rankB = self::TYPE_RANK[$b->type] ?? 99;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        return ($a->order ?? PHP_INT_MAX) <=> ($b->order ?? PHP_INT_MAX);
    }

    private function menuSearchNeedle(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $trimmed = trim($search);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Ids of menus matching the search term (name in any supported locale, or
     * type) — the same match surface as the previous flat lookup.
     *
     * @param  Collection<string, Menu>  $menus
     * @return array<string, true>
     */
    private function menuMatchIds(Collection $menus, string $needle): array
    {
        $locales = \App\Support\Localization\LocalePreference::supported();

        return $menus
            ->filter(function (Menu $menu) use ($needle, $locales): bool {
                $name = $menu->getTranslations('name');
                foreach ($locales as $locale) {
                    if (isset($name[$locale]) && stripos((string) $name[$locale], $needle) !== false) {
                        return true;
                    }
                }

                return stripos($menu->type, $needle) !== false;
            })
            ->map(fn (Menu $menu) => $menu->id)
            ->values()
            ->flip()
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function roles(?string $search, int $page, int $perPage, array $ids = []): array
    {
        if ($ids !== []) {
            return $this->hydrateRoles($ids);
        }

        $query = Role::query()
            ->core()
            ->where('guard_name', 'api')
            ->orderBy('level');

        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where('name', 'like', $like);
        }

        $offset = ($page - 1) * $perPage;
        $rows = (clone $query)->skip($offset)->take($perPage + 1)->get();
        $more = $rows->count() > $perPage;

        if ($more) {
            $rows = $rows->take($perPage);
        }

        return [
            'results' => $rows->map(fn (Role $role) => [
                'id' => (string) $role->id,
                'text' => (string) $role->name,
            ])->values()->all(),
            'pagination' => ['more' => $more],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function hydrateUsers(User $actor, array $ids): array
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return $this->emptyPage(false);
        }

        $users = $this->usersVisibleToActorQuery($actorLevel)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();

        return [
            'results' => $users->map(fn (User $user) => $this->userResult($user))->values()->all(),
            'pagination' => ['more' => false],
        ];
    }

    /**
     * Flat pre-selected rows for the hydrate path (`ids[]`), carrying grouping
     * metadata (group/parent_text/level) so the SPA can render the selection
     * with context without fetching the whole tree.
     *
     * @return array{results: list<array{id: string, text: string, group: string, parent_text: string|null, level: int}>, pagination: array{more: bool}}
     */
    private function hydrateMenus(array $ids, string $layout, ?string $excludeId): array
    {
        $menus = Menu::query()
            ->with('parent')
            ->whereIn('id', $ids)
            ->where('layout', $layout)
            ->when($excludeId !== null, fn (Builder $builder) => $builder->where('id', '!=', $excludeId))
            ->orderBy('type')
            ->orderBy('order')
            ->get();

        return [
            'results' => $menus->map(fn (Menu $menu) => $this->menuResult($menu))->values()->all(),
            'pagination' => ['more' => false],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function hydrateRoles(array $ids): array
    {
        $roles = Role::query()
            ->core()
            ->where('guard_name', 'api')
            ->whereIn('id', $ids)
            ->orderBy('level')
            ->get();

        return [
            'results' => $roles->map(fn (Role $role) => [
                'id' => (string) $role->id,
                'text' => (string) $role->name,
            ])->values()->all(),
            'pagination' => ['more' => false],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function paginateUsers(Builder $query, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $rows = (clone $query)->skip($offset)->take($perPage + 1)->get();
        $more = $rows->count() > $perPage;

        if ($more) {
            $rows = $rows->take($perPage);
        }

        return [
            'results' => $rows->map(fn (User $user) => $this->userResult($user))->values()->all(),
            'pagination' => ['more' => $more],
        ];
    }

    private function usersVisibleToActorQuery(int $actorLevel): Builder
    {
        $userModel = new User;
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        return User::query()
            ->whereIn('id', function ($query) use ($actorLevel, $userModel, $morphKey, $rolesTable, $pivotTable): void {
                $query->select($pivotTable.'.'.$morphKey)
                    ->from($pivotTable)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
                    ->where("{$pivotTable}.model_type", $userModel->getMorphClass())
                    ->where("{$rolesTable}.domain_kind", 'core')
                    ->whereNotNull("{$rolesTable}.level")
                    ->groupBy("{$pivotTable}.{$morphKey}")
                    ->havingRaw('MIN('.$rolesTable.'.level) >= ?', [$actorLevel]);
            });
    }

    /**
     * @return array{id: string, text: string}
     */
    private function userResult(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'text' => $user->name.' ('.$user->email.')',
        ];
    }

    /**
     * Sibling sort priority by type — mirrors MenuRepository::TYPE_RANK so the
     * lookup order matches how the Dynamic Menu sidebar renders its tree.
     */
    private const TYPE_RANK = ['h' => 0, 'p' => 1, 'c' => 2];

    /**
     * Flat Select2 result for a pre-selected menu (hydrate path). Carries
     * grouping metadata so the SPA renders the selection with group context:
     * `group` is the top-level ancestor header (optgroup label), `parent_text`
     * the direct parent label, `level` the depth (0 = top level).
     *
     * @return array{id: string, text: string, group: string, parent_text: string|null, level: int}
     */
    private function menuResult(Menu $menu): array
    {
        $name = TranslationResolver::resolve($menu->getTranslations('name'));

        return [
            'id' => (string) $menu->id,
            'text' => $name.' · '.$menu->type,
            'group' => $this->menuGroupLabel($menu),
            'parent_text' => $menu->parent !== null
                ? TranslationResolver::resolve($menu->parent->getTranslations('name')).' · '.$menu->parent->type
                : null,
            'level' => $this->menuLevel($menu),
        ];
    }

    private function menuGroupLabel(Menu $menu): string
    {
        $ancestor = $menu;

        while ($ancestor->parent_id !== null) {
            $ancestor = $ancestor->parent ?? $ancestor->parent()->first();

            if ($ancestor === null) {
                break;
            }
        }

        if ($ancestor === null) {
            return TranslationResolver::resolve($menu->getTranslations('name')).' · '.$menu->type;
        }

        return TranslationResolver::resolve($ancestor->getTranslations('name')).' · '.$ancestor->type;
    }

    private function menuLevel(Menu $menu): int
    {
        $level = 0;
        $ancestor = $menu;

        while ($ancestor->parent_id !== null) {
            $ancestor = $ancestor->parent ?? $ancestor->parent()->first();

            if ($ancestor === null) {
                break;
            }

            $level++;
        }

        return $level;
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function emptyPage(bool $more = false): array
    {
        return [
            'results' => [],
            'pagination' => ['more' => $more],
        ];
    }
}
