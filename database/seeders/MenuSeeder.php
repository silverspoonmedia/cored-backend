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
 * registered WEB route names. Menu names are stored as Spatie translatable JSON.
 */
class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        $managementRoleIds = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['Root', 'Admin', 'Moderator', 'Management'])
            ->pluck('id')
            ->all();

        $main = Menu::query()->create([
            'parent_id' => null,
            'name' => ['en' => 'Menu', 'id' => 'Menu'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $main->id,
            'name' => ['en' => 'Dashboard', 'id' => 'Dasbor'],
            'icon' => 'bi bi-speedometer2',
            'route' => 'dashboard',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        $usersItem = Menu::query()->create([
            'parent_id' => $main->id,
            'name' => ['en' => 'User management', 'id' => 'Manajemen Pengguna'],
            'icon' => 'bi bi-people',
            'route' => 'users.index',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $usersItem->roles()->sync($managementRoleIds);

        $admin = Menu::query()->create([
            'parent_id' => null,
            'name' => ['en' => 'Administration', 'id' => 'Administrasi'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        $menus = Menu::query()->create([
            'parent_id' => $admin->id,
            'name' => ['en' => 'Navigation menu', 'id' => 'Navigasi Menu'],
            'icon' => 'bi bi-list-nested',
            'route' => null,
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);
        $menus->roles()->sync($managementRoleIds);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'name' => ['en' => 'Menu list', 'id' => 'Daftar Menu'],
            'icon' => 'bi bi-circle',
            'route' => 'menus.index',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'name' => ['en' => 'Trashed menus', 'id' => 'Menu Terhapus'],
            'icon' => 'bi bi-trash',
            'route' => 'menus.trashed',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }
}
