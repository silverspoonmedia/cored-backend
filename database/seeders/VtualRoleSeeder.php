<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Database\Seeder;

/**
 * vTual product RBAC (domain_kind = product) + default platforms seed.
 */
class VtualRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';
        $domainKind = DomainKind::Product->value;

        // Levels unique per domain_kind. File Hosting uses 1100–1300; offset Vtual.
        $roles = [
            'Vtual Admin' => 2100,
            'Vtual Editor' => 2200,
            'Vtual vTuber' => 2250,
            'Vtual Viewer' => 2300,
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
            'view vtual creator',
            'create vtual creator',
            'edit vtual creator',
            'delete vtual creator',
            'view vtual management',
            'create vtual management',
            'edit vtual management',
            'manage vtual management members',
            'view vtual channel',
            'create vtual channel',
            'claim vtual creator',
        ];

        $anyPermissions = [
            'view any vtual resource',
            'manage any vtual resource',
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
            'Vtual Admin' => $allPermissions,
            'Vtual Editor' => $ownPermissions,
            'Vtual vTuber' => $ownPermissions,
            'Vtual Viewer' => [
                'view vtual creator',
                'view vtual management',
                'view vtual channel',
                'claim vtual creator',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role !== null) {
                $role->syncPermissions($permissionNames);
            }
        }

        $userRole = Role::query()
            ->where('name', 'User')
            ->where('guard_name', $guard)
            ->where('domain_kind', DomainKind::Core)
            ->first();

        if ($userRole !== null) {
            $userRole->givePermissionTo($ownPermissions);
        }

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

        app(PlatformService::class)->seedDefaults();
    }
}
