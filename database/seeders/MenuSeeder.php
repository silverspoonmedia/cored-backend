<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Support\Navigation\MenuLayout;
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
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Menu', 'id' => 'Menu', 'ja' => 'メニュー'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Dashboard', 'id' => 'Beranda', 'ja' => 'ダッシュボード'],
            'icon' => 'bi bi-speedometer2',
            'route' => 'dashboard',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        $usersItem = Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'User management', 'id' => 'Manajemen Pengguna', 'ja' => 'ユーザー管理'],
            'icon' => 'bi bi-people',
            'route' => 'users.index',
            'type' => 'p',
            'order' => 2,
            'is_authenticate' => true,
        ]);
        $usersItem->roles()->sync($managementRoleIds);

        // Notifications: every authenticated user may receive personal notifications,
        // so no role gating — visible to all signed-in viewers.
        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Notifications', 'id' => 'Notifikasi', 'ja' => '通知'],
            'icon' => 'bi bi-bell',
            'route' => 'notifications.index',
            'type' => 'p',
            'order' => 3,
            'is_authenticate' => true,
        ]);

        // Master-detail playground reference (Order + OrderDetail). No RBAC in the
        // playground, so it is visible to any authenticated viewer.
        Menu::query()->create([
            'parent_id' => $main->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Orders', 'id' => 'Pesanan', 'ja' => '注文'],
            'icon' => 'bi bi-cart',
            'route' => 'orders.index',
            'type' => 'p',
            'order' => 4,
            'is_authenticate' => true,
        ]);

        $admin = Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Administration', 'id' => 'Administrasi', 'ja' => '管理'],
            'icon' => null,
            'route' => null,
            'type' => 'h',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        $menus = Menu::query()->create([
            'parent_id' => $admin->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Navigation menu', 'id' => 'Navigasi Menu', 'ja' => 'ナビゲーションメニュー'],
            'icon' => 'bi bi-list-nested',
            'route' => null,
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);
        $menus->roles()->sync($managementRoleIds);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Menu list', 'id' => 'Daftar Menu', 'ja' => 'メニュー一覧'],
            'icon' => 'bi bi-circle',
            'route' => 'menus.index',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $menus->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Trashed menus', 'id' => 'Menu Terhapus', 'ja' => '削除済みメニュー'],
            'icon' => 'bi bi-trash',
            'route' => 'menus.trashed',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
        ]);

        // Top navbar shortcuts (layout=navbar). Headers flatten in the bar renderer.
        Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::NAVBAR,
            'name' => ['en' => 'Home', 'id' => 'Beranda', 'ja' => 'ホーム'],
            'icon' => 'bi bi-house',
            'route' => 'dashboard',
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => true,
        ]);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }
}
