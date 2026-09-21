<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds platform (core) roles and permissions.
 *
 * Product domains should add their own seeders (e.g. ArchivdRoleSeeder) with
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
            'view error log',
            'view payment gateway',
            'manage payment gateway',
            'view inference catalog',
            'manage inference catalog',
            'view ai connections',
            'manage ai connections',
            'view invoice',
            'initiate payment',
            'view subscription',
            'manage subscription',
            'view service',
            'manage service',
            'purchase service',
            'view product activations',
            'manage product activations',
            'view vtual platform credentials',
            'manage vtual platform credentials',
            'view domain access',
            'manage domain access',
            'view settings',
            'manage settings',
            'view posts',
            'manage posts',
            'restore posts',
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
            'Root' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view error log', 'view payment gateway', 'manage payment gateway', 'view inference catalog', 'manage inference catalog', 'view ai connections', 'manage ai connections', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'manage service', 'purchase service', 'view product activations', 'manage product activations', 'view vtual platform credentials', 'manage vtual platform credentials', 'view domain access', 'manage domain access', 'view settings', 'manage settings'],
            'Admin' => ['view user', 'create user', 'edit user', 'delete user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view activity log', 'view error log', 'view payment gateway', 'manage payment gateway', 'view inference catalog', 'manage inference catalog', 'view ai connections', 'manage ai connections', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'manage service', 'purchase service', 'view product activations', 'manage product activations', 'view vtual platform credentials', 'manage vtual platform credentials', 'view domain access', 'manage domain access', 'view settings', 'manage settings', 'view posts', 'manage posts', 'restore posts'],
            'Moderator' => ['view user', 'create user', 'edit user', 'view menu', 'manage menu', 'restore menu', 'view notification', 'view payment gateway', 'view inference catalog', 'view ai connections', 'view invoice', 'initiate payment', 'view subscription', 'manage subscription', 'view service', 'purchase service', 'view product activations', 'view posts', 'manage posts', 'restore posts'],
            'Management' => ['view user', 'view menu', 'view notification', 'view payment gateway', 'view inference catalog', 'view ai connections', 'view invoice', 'view subscription', 'view service', 'view product activations', 'view posts'],
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
