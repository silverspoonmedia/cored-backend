<?php

namespace App\Repositories\Contracts;

use App\Models\Menu;
use App\Models\User;

interface MenuRepositoryInterface
{
    /**
     * Build the auth-aware navigation tree for the given user (null = guest).
     *
     * @return list<array<string, mixed>>
     */
    public function navigationTree(?User $user): array;

    /**
     * Hierarchically sorted flat list for admin/management screens.
     *
     * @return list<Menu>
     */
    public function adminFlatList(?string $loadType): array;

    public function read(string $id): Menu;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMenu(array $data, ?string $position, ?string $referenceId): Menu;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMenu(Menu $menu, array $data): Menu;

    public function updateMenuPosition(Menu $menu, string $position, string $referenceId): Menu;

    /**
     * Soft-delete a menu. When it has descendants, $cascade must be true to
     * soft-delete the whole subtree; otherwise the call is rejected.
     */
    public function deleteMenu(Menu $menu, bool $cascade = false): void;

    /**
     * Forget the cached navigation snapshot (e.g. after a role rename).
     */
    public function flushNavigationCache(): void;

    /**
     * @param  list<string>  $idsOrNames
     */
    public function syncRoles(Menu $menu, array $idsOrNames): void;

    /**
     * @param  list<string>  $userIds
     */
    public function syncUserIncludes(Menu $menu, array $userIds): void;

    /**
     * @param  list<string>  $userIds
     */
    public function syncUserExcludes(Menu $menu, array $userIds): void;
}
