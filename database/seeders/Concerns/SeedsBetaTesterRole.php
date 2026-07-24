<?php

namespace Database\Seeders\Concerns;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Support\DomainAccess\ProductNames;

/**
 * Seeds the per-product "Beta tester {Domain}" role + "access {domain} product"
 * permission used by the Private domain-access gate.
 *
 * Beta roles are domain_kind = product with level = null: they never take part
 * in the H-RBAC ladder (RoleService ignores null levels) and null avoids the
 * unique(domain_kind, level) constraint entirely, so any number of product
 * domains can seed their own beta role without hand-picking level offsets.
 *
 * The access permission is also granted to core Root/Admin so privileged staff
 * keep reach even while a domain is Private (they also bypass via role check).
 */
trait SeedsBetaTesterRole
{
    protected function seedBetaTesterRole(string $productKey, string $guard = 'api'): void
    {
        $roleName = ProductNames::betaRole($productKey);
        $permissionName = ProductNames::accessPermission($productKey);

        $role = Role::query()->updateOrCreate(
            ['name' => $roleName, 'guard_name' => $guard],
            [
                'level' => null,
                'domain_kind' => DomainKind::Product->value,
            ],
        );

        Permission::query()->updateOrCreate(
            ['name' => $permissionName, 'guard_name' => $guard],
            ['domain_kind' => DomainKind::Product->value],
        );

        $role->givePermissionTo($permissionName);

        foreach (['Root', 'Admin'] as $coreRoleName) {
            $coreRole = Role::query()
                ->where('name', $coreRoleName)
                ->where('guard_name', $guard)
                ->where('domain_kind', DomainKind::Core)
                ->first();

            $coreRole?->givePermissionTo($permissionName);
        }
    }
}
