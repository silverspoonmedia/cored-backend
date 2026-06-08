<?php

namespace App\Support\Navigation;

use App\Models\Menu;

class MenuPresenter
{
    /**
     * Flat admin row including RBAC assignments.
     *
     * @return array<string, mixed>
     */
    public static function presentAdminRow(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'name' => $menu->name,
            'icon' => $menu->icon,
            'route' => $menu->route,
            'type' => $menu->type,
            'order' => $menu->order,
            'is_authenticate' => $menu->is_authenticate,
            'is_guest_only' => $menu->is_guest_only,
            'role_ids' => $menu->roles->pluck('id')->values()->all(),
            'user_include_ids' => $menu->includedUsers->pluck('id')->values()->all(),
            'user_exclude_ids' => $menu->excludedUsers->pluck('id')->values()->all(),
        ];
    }

    /**
     * @param  iterable<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    public static function presentAdminCollection(iterable $menus): array
    {
        $rows = [];

        foreach ($menus as $menu) {
            $rows[] = self::presentAdminRow($menu);
        }

        return $rows;
    }

    /**
     * Admin row plus the deletion timestamp, for the trashed listing.
     *
     * @return array<string, mixed>
     */
    public static function presentTrashedRow(Menu $menu): array
    {
        return array_merge(self::presentAdminRow($menu), [
            'deleted_at' => $menu->deleted_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  iterable<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    public static function presentTrashedCollection(iterable $menus): array
    {
        $rows = [];

        foreach ($menus as $menu) {
            $rows[] = self::presentTrashedRow($menu);
        }

        return $rows;
    }

    /**
     * Single-record detail (used after create/update/show).
     *
     * @return array<string, mixed>
     */
    public static function presentDetail(Menu $menu): array
    {
        return array_merge(self::presentAdminRow($menu), [
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ]);
    }
}
