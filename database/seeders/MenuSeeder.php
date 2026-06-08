<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        $header = Menu::query()->create([
            'parent_id' => null,
            'name' => 'Application',
            'icon' => 'folder',
            'route' => null,
            'type' => 'h',
            'order' => 1,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ]);

        $auth = Menu::query()->create([
            'parent_id' => $header->id,
            'name' => 'Auth',
            'icon' => 'lock',
            'route' => null,
            'type' => 'p',
            'order' => 1,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ]);

        Menu::query()->create([
            'parent_id' => $auth->id,
            'name' => 'Login',
            'icon' => 'sign-in-alt',
            'route' => 'api.v1.auth.login',
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => false,
            'is_guest_only' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $auth->id,
            'name' => 'Register',
            'icon' => 'user-plus',
            'route' => 'api.v1.auth.register',
            'type' => 'c',
            'order' => 2,
            'is_authenticate' => false,
            'is_guest_only' => true,
        ]);

        Menu::query()->create([
            'parent_id' => $auth->id,
            'name' => 'Profile',
            'icon' => 'user',
            'route' => 'api.v1.auth.me',
            'type' => 'c',
            'order' => 3,
            'is_authenticate' => true,
            'is_guest_only' => null,
        ]);
    }
}
