<?php

namespace Database\Seeders;

use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\Models\ProductDomainVisibility;
use Database\Seeders\Concerns\SeedsBetaTesterRole;
use Illuminate\Database\Seeder;

/**
 * RBAC + access seeder for the ZzDemo product domain.
 *
 * Seeds the "Beta tester ZzDemo" role (domain_kind = product) with its
 * "access ... product" permission and a Public visibility row so the domain
 * is reachable by default. Flip it to Private from the admin UI when ready.
 *
 * Add the domain's own H-RBAC roles/permissions below as the module grows.
 */
class ZzDemoRoleSeeder extends Seeder
{
    use SeedsBetaTesterRole;

    public function run(): void
    {
        $this->seedBetaTesterRole('ZzDemo');

        ProductDomainVisibility::query()->updateOrCreate(
            ['product_key' => 'ZzDemo'],
            ['visibility' => DomainVisibility::Public->value],
        );
    }
}
