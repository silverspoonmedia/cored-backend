<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
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
            'restore menu',
            'view notification',
            'view activity log',
            'view payment gateway',
            'manage payment gateway',
            'view invoice',
            'initiate payment',
            'view subscription',
            'manage subscription',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $rolePermissions = [
            'Root' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view payment gateway', 'manage payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription'],
            'Admin' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view payment gateway', 'manage payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription'],
            'Moderator' => ['view user', 'create user', 'edit user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription'],
            'Management' => ['view user', 'view menu', 'view notification', 'view payment gateway', 'view invoice', 'view subscription'],
            'User' => ['view notification'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }
    }
}
