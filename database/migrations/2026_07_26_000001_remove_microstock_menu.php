<?php

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove the stale dynamic Microstock sidebar menu. Microstock is now reached
 * via /services (activation) and the Archivd workspace switcher, not a
 * dedicated sidebar entry. The seeder is also cleaned so fresh installs never
 * re-create it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Menu::query()->where('route', 'microstock.index')->each(function (Menu $menu): void {
            $menu->roles()->detach();
            $menu->delete();
        });

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }

    public function down(): void
    {
        // No-op: MenuSeeder is the source of truth for re-adding menus.
    }
};
