<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Product-domain RBAC catalog (domain_kind = product).
 *
 * Spatie names stay globally unique — never reuse core names (Admin, view user, …).
 * Product levels are independent of the core H-RBAC ladder.
 */
class ProductRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';
        $domainKind = DomainKind::Product->value;

        $roles = [
            'Product Admin' => 100,
            'Product Editor' => 200,
            'Product Viewer' => 300,
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
            'view product item',
            'create product item',
            'edit product item',
            'delete product item',
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
            'Product Admin' => $permissions,
            'Product Editor' => [
                'view product item',
                'create product item',
                'edit product item',
            ],
            'Product Viewer' => [
                'view product item',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }

        // Cross-kind pivot: core Admin may manage product items without a product role.
        $coreAdmin = Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', $guard)
            ->where('domain_kind', DomainKind::Core)
            ->first();

        if ($coreAdmin !== null) {
            $coreAdmin->givePermissionTo($permissions);
        }
    }
}
