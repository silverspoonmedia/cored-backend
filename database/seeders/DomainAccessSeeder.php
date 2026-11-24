<?php

namespace Database\Seeders;

use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\Models\ProductDomainVisibility;
use Database\Seeders\Concerns\SeedsBetaTesterRole;
use Illuminate\Database\Seeder;

/**
 * Seeds domain-access visibility + Beta tester roles for the product domains a host
 * installs. The boilerplate ships none, so the loop body never runs; `domain:make`
 * generates a `{Name}RoleSeeder` per product instead, and this seeder is the place a
 * host lists the products whose visibility and tester role it wants at install time.
 *
 * Existing domains default to Public so current behavior is unchanged.
 */
class DomainAccessSeeder extends Seeder
{
    use SeedsBetaTesterRole;

    /** @var list<string> */
    private const PRODUCT_DOMAINS = [];

    public function run(): void
    {
        /** @var list<string> $productDomains */
        $productDomains = self::PRODUCT_DOMAINS;

        foreach ($productDomains as $productKey) {
            $this->seedBetaTesterRole($productKey);

            ProductDomainVisibility::query()->updateOrCreate(
                ['product_key' => $productKey],
                ['visibility' => DomainVisibility::Public->value],
            );
        }
    }
}
