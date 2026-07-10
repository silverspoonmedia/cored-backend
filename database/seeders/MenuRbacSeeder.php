<?php

namespace Database\Seeders;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Debugging seeder that populates the three menu RBAC pivot tables
 * (`menu_roles`, `menu_user_includes`, `menu_user_excludes`) with a small,
 * self-contained "Debug" branch so the per-viewer visibility rules can be
 * exercised by hand or in tinker. Idempotent: skips if the branch exists.
 */
class MenuRbacSeeder extends Seeder
{
    use WithoutModelEvents;

    private const GUARD = 'api';

    public function run(): void
    {
        // Marker: if the debug header already exists, assume the branch is seeded.
        if (Menu::query()->where('name->en', 'Debug')->where('type', 'h')->exists()) {
            return;
        }

        [$includeUser, $excludeUser] = $this->demoUsers();

        $header = Menu::query()->create([
            'parent_id' => null,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Debug', 'id' => 'Debug', 'ja' => 'デバッグ'],
            'icon' => 'bi bi-bug',
            'route' => null,
            'type' => 'h',
            'order' => 99,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ]);

        $parent = Menu::query()->create([
            'parent_id' => $header->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'RBAC Demo', 'id' => 'Demo RBAC', 'ja' => 'RBAC デモ'],
            'icon' => 'bi bi-shield-lock',
            'route' => null,
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ]);

        // menu_roles: visible only to Admin/Root holders.
        $adminOnly = Menu::query()->create([
            'parent_id' => $parent->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Admin Only', 'id' => 'Hanya Admin', 'ja' => '管理者のみ'],
            'icon' => 'bi bi-person-badge',
            'route' => null,
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => true,
            'is_guest_only' => null,
        ]);
        $adminOnly->roles()->sync($this->roleIds(['Admin', 'Root']));

        // menu_user_includes: visible only to the explicitly included user.
        $includedOnly = Menu::query()->create([
            'parent_id' => $parent->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Included User Only', 'id' => 'Hanya Pengguna Termasuk', 'ja' => '対象ユーザーのみ'],
            'icon' => 'bi bi-person-check',
            'route' => null,
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => true,
            'is_guest_only' => null,
        ]);
        $includedOnly->includedUsers()->sync([$includeUser->id]);

        // menu_user_excludes: hidden from the excluded user, visible to everyone else.
        $hiddenForExcluded = Menu::query()->create([
            'parent_id' => $parent->id,
            'layout' => MenuLayout::SIDEBAR,
            'name' => ['en' => 'Hidden For Excluded User', 'id' => 'Tersembunyi Untuk Pengguna Dikecualikan', 'ja' => '除外ユーザーには非表示'],
            'icon' => 'bi bi-person-x',
            'route' => null,
            'type' => 'c',
            'order' => 3,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ]);
        $hiddenForExcluded->excludedUsers()->sync([$excludeUser->id]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function demoUsers(): array
    {
        $include = $this->demoUser('menu-include@example.com', 'Menu Include Demo');
        $exclude = $this->demoUser('menu-exclude@example.com', 'Menu Exclude Demo');

        return [$include, $exclude];
    }

    private function demoUser(string $email, string $name): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('123456789'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('User')) {
            $user->assignRole('User');
        }

        return $user;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function roleIds(array $names): array
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();
    }
}
