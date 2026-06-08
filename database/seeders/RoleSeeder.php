<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';

        $roles = [
            'Root' => 1,
            'Admin' => 100,
            'Moderator' => 200,
            'Management' => 300,
            'User' => 400,
        ];

        foreach ($roles as $name => $level) {
            Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                ['level' => $level],
            );
        }

        $permissions = [
            'view user',
            'create user',
            'edit user',
            'delete user',
            'view menu',
            'manage menu',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $rolePermissions = [
            'Root' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu'],
            'Admin' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu'],
            'Moderator' => ['view user', 'create user', 'edit user', 'view menu', 'manage menu'],
            'Management' => ['view user', 'view menu'],
            'User' => [],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }
    }
}
