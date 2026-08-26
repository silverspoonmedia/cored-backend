<?php

namespace App\Domain\Navigation\Services;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Support\MenuLayout;
use App\Domain\Navigation\Support\NavigationRealtime;
use App\Domain\Navigation\Support\NavigationTreeFilter;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function __construct(
        private readonly MenuRepositoryInterface $menus,
        private readonly NavigationRealtime $realtime,
    ) {}

    /**
     * Auth-aware navigation tree for the current request (null = guest).
     *
     * @return list<array<string, mixed>>
     */
    public function navigationTree(?User $user, ?string $search = null, ?string $layout = null): array
    {
        $tree = $this->menus->navigationTree($user, $layout ?? MenuLayout::default());

        if ($search === null || trim($search) === '') {
            return $tree;
        }

        return app(NavigationTreeFilter::class)->filter($tree, $search);
    }

    /**
     * @return list<Menu>
     */
    public function adminList(?string $loadType, ?string $layout = null): array
    {
        return $this->menus->adminFlatList($loadType, $layout);
    }

    public function detail(string $id): Menu
    {
        return $this->menus->read($id);
    }

    /**
     * @param  array<string, mixed>  $data  Validated request data.
     */
    public function create(array $data): Menu
    {
        return DB::transaction(function () use ($data) {
            $menu = $this->menus->createMenu(
                $this->mapAttributes($data, true),
                $data['position'] ?? 'append',
                $data['reference_id'] ?? null,
            );

            // On create, relationships are always (re)set so omitted keys clear them.
            $this->menus->syncRoles($menu, $data['roles'] ?? []);
            $this->menus->syncUserIncludes($menu, $data['user_includes'] ?? []);
            $this->menus->syncUserExcludes($menu, $data['user_excludes'] ?? []);

            $created = $this->menus->read($menu->id);
            $this->realtime->menuTreeChanged($created->layout, 'created');

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated request data (partial).
     */
    public function update(Menu $menu, array $data): Menu
    {
        return DB::transaction(function () use ($menu, $data) {
            $attributes = $this->mapAttributes($data, false);

            if ($attributes !== []) {
                $menu = $this->menus->updateMenu($menu, $attributes);
            }

            if ($this->shouldReposition($data)) {
                $menu = $this->menus->updateMenuPosition(
                    $menu,
                    (string) $data['position'],
                    (string) $data['reference_id'],
                );
            }

            // On update, only touch a relationship when its key was sent.
            if (array_key_exists('roles', $data)) {
                $this->menus->syncRoles($menu, $data['roles']);
            }
            if (array_key_exists('user_includes', $data)) {
                $this->menus->syncUserIncludes($menu, $data['user_includes']);
            }
            if (array_key_exists('user_excludes', $data)) {
                $this->menus->syncUserExcludes($menu, $data['user_excludes']);
            }

            $updated = $this->menus->read($menu->id);
            $this->realtime->menuTreeChanged($updated->layout, 'updated');

            return $updated;
        });
    }

    public function delete(Menu $menu, bool $cascade = false): void
    {
        $layout = $menu->layout;
        $this->menus->deleteMenu($menu, $cascade);
        $this->realtime->menuTreeChanged($layout, 'deleted');
    }

    public function restore(Menu $menu): void
    {
        $this->menus->restoreMenu($menu);
        $this->realtime->menuTreeChanged($menu->layout, 'restored');
    }

    /**
     * @return list<Menu>
     */
    public function trashedList(?string $loadType, ?string $layout = null): array
    {
        return $this->menus->trashedList($loadType, $layout);
    }

    /**
     * Translate request field names to persisted column names.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapAttributes(array $data, bool $isCreate): array
    {
        $attributes = [];

        if ($isCreate) {
            $attributes['name'] = $data['name'];
            $attributes['type'] = $data['type'];
            $attributes['layout'] = $data['layout'] ?? MenuLayout::default();
            $attributes['description'] = $data['description'] ?? null;
        } else {
            if (array_key_exists('name', $data)) {
                $attributes['name'] = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $attributes['description'] = $data['description'];
            }
            if (array_key_exists('type', $data)) {
                $attributes['type'] = $data['type'];
            }
        }

        if ($isCreate || array_key_exists('icon', $data)) {
            $attributes['icon'] = $data['icon'] ?? null;
        }
        if ($isCreate || array_key_exists('route', $data)) {
            $attributes['route'] = $data['route'] ?? null;
        }
        if ($isCreate || array_key_exists('hash', $data)) {
            $attributes['hash'] = $data['hash'] ?? null;
        }
        if ($isCreate || array_key_exists('parent', $data)) {
            $attributes['parent_id'] = $data['parent'] ?? null;
        }
        if (array_key_exists('authenticate', $data)) {
            $attributes['is_authenticate'] = (bool) $data['authenticate'];
        }
        if (array_key_exists('guest_only', $data)) {
            $attributes['is_guest_only'] = (bool) $data['guest_only'];
        }

        return $attributes;
    }

    /**
     * Reposition only when the client explicitly sends a before/after pair.
     * Omitting placement (or sending append on create) leaves order unchanged.
     *
     * @param  array<string, mixed>  $data
     */
    private function shouldReposition(array $data): bool
    {
        return in_array($data['position'] ?? null, ['before', 'after'], true)
            && filled($data['reference_id'] ?? null);
    }
}
