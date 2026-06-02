<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';

        $permissions = [
            'users.view',
            'users.manage',
            'profile.view',
            'profile.update',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $userRole = Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => $guard,
        ]);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $userRole->syncPermissions(['profile.view', 'profile.update']);
        $adminRole->syncPermissions(Permission::query()->where('guard_name', $guard)->pluck('name'));
    }
}
