<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds platform (core) roles and permissions.
 *
 * Product domains should add their own seeders (e.g. FileHostingRoleSeeder) with
 * domain_kind = product and their own level ladder. Spatie still requires unique
 * (name, guard_name) across kinds — do not reuse core role names like "Admin".
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';
        $domainKind = DomainKind::Core->value;

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
                [
                    'level' => $level,
                    'domain_kind' => $domainKind,
                ],
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
            'view service',
            'manage service',
            'purchase service',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => $guard,
                ],
                [
                    'domain_kind' => $domainKind,
                ],
            );
        }

        $rolePermissions = [
            'Root' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view payment gateway', 'manage payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'manage service', 'purchase service'],
            'Admin' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view payment gateway', 'manage payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'manage service', 'purchase service'],
            'Moderator' => ['view user', 'create user', 'edit user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view payment gateway', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'purchase service'],
            'Management' => ['view user', 'view menu', 'view notification', 'view payment gateway', 'view invoice', 'view subscription', 'view service'],
            'User' => ['view notification', 'view service', 'purchase service'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }
    }
}
