<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the default admin web navigation that the dynamic sidebar renders.
 *
 * Icons are full Bootstrap Icons classes (e.g. "bi bi-people"); `route` holds
 * registered WEB route names. Visibility mirrors the page-level authorization:
 * the engine gates by ROLE, so items behind `view user` / `view menu` get the
 * roles that actually hold those permissions attached (Root/Admin/Moderator/
 * Management). Dashboard stays open to every authenticated viewer.
 */
class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        // Roles that may see the management items (those holding view user / view menu).
        $managementRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator', 'Management'])
            ->pluck('id')
            ->all();

        // The engine prunes childless 'h'/'p' nodes and sorts siblings by type
        // rank (h→p→c) before `order`. So leaf links must be 'c' (never pruned),
        // and a branch ('p') must not share a parent with leaves or it floats
        // above them. The clean fit: group leaves under one header, the branch
        // under its own — each group stays single-type, so `order` alone wins.

        $main = Menu::query()->create([
            'parent_id' => null,
            'name' => 'Menu',
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $main->id,
            'name' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'route' => 'dashboard',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        $usersItem = Menu::query()->create([
            'parent_id' => $main->id,
            'name' => 'Manajemen Pengguna',
            'icon' => 'bi bi-people',
            'route' => 'users.index',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $usersItem->roles()->sync($managementRoleIds);

        $admin = Menu::query()->create([
            'parent_id' => null,
            'name' => 'Administrasi',
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        $menus = Menu::query()->create([
            'parent_id' => $admin->id,
            'name' => 'Navigasi Menu',
            'icon' => 'bi bi-list-nested',
            'route' => null,
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);
        $menus->roles()->sync($managementRoleIds);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'name' => 'Daftar Menu',
            'icon' => 'bi bi-circle',
            'route' => 'menus.index',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'name' => 'Menu Terhapus',
            'icon' => 'bi bi-trash',
            'route' => 'menus.trashed',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        // Events are suppressed in seeders, so bust the navigation snapshot by hand.
        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }
}
