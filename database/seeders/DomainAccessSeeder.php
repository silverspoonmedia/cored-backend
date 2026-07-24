<?php

namespace Database\Seeders;

use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\Models\ProductDomainVisibility;
use Database\Seeders\Concerns\SeedsBetaTesterRole;
use Illuminate\Database\Seeder;

/**
 * Seeds domain-access visibility + Beta tester roles for the existing product
 * domains (FileHosting, Vtual). New domains created via domain:make get their
 * own generated {Name}RoleSeeder instead.
 *
 * Existing domains default to Public so current behavior is unchanged.
 */
class DomainAccessSeeder extends Seeder
{
    use SeedsBetaTesterRole;

    /** @var list<string> */
    private const PRODUCT_DOMAINS = ['FileHosting', 'Vtual'];

    public function run(): void
    {
        foreach (self::PRODUCT_DOMAINS as $productKey) {
            $this->seedBetaTesterRole($productKey);

            ProductDomainVisibility::query()->updateOrCreate(
                ['product_key' => $productKey],
                ['visibility' => DomainVisibility::Public->value],
            );
        }
    }
}
