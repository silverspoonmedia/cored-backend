<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Archivd product RBAC (domain_kind = product).
 *
 * Product roles are independent of core H-RBAC. Cross-user “any” permissions
 * are granted to core internal roles; hierarchy still filters who they can touch.
 */
class ArchivdRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';
        $domainKind = DomainKind::Product->value;

        // Levels unique per domain_kind (shared product ladder across modules).
        // Product catalog already uses 100/200/300 — offset Archivd.
        $roles = [
            'Archivd Admin' => 1100,
            'Archivd Editor' => 1200,
            'Archivd Viewer' => 1300,
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

        $ownPermissions = [
            'view archivd folder',
            'create archivd folder',
            'edit archivd folder',
            'delete archivd folder',
            'view archivd file',
            'create archivd file',
            'edit archivd file',
            'delete archivd file',
        ];

        $anyPermissions = [
            'view any archivd resource',
            'manage any archivd resource',
        ];

        $allPermissions = array_merge($ownPermissions, $anyPermissions);

        foreach ($allPermissions as $permission) {
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
            'Archivd Admin' => $allPermissions,
            'Archivd Editor' => $ownPermissions,
            'Archivd Viewer' => [
                'view archivd folder',
                'view archivd file',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }

        // Regular User: own CRUD only (requirement #5).
        $userRole = Role::query()
            ->where('name', 'User')
            ->where('guard_name', $guard)
            ->where('domain_kind', DomainKind::Core)
            ->first();

        if ($userRole !== null) {
            $userRole->givePermissionTo($ownPermissions);
        }

        // Internal core roles: own + cross-user manage (requirement #6).
        $internalRoles = ['Root', 'Admin', 'Moderator', 'Management'];

        foreach ($internalRoles as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guard)
                ->where('domain_kind', DomainKind::Core)
                ->first();

            if ($role !== null) {
                $role->givePermissionTo($allPermissions);
            }
        }
    }
}
