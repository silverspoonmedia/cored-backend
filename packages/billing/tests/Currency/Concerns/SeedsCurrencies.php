<?php

namespace App\Domain\Currency\Tests\Concerns;

use App\Domain\Currency\Database\Seeders\CurrencySeeder;

/**
 * The billing package's no-argument `seedCurrencies()`.
 *
 * The kernel trait takes the seeder as a parameter — the kernel may not name a
 * sibling package — so a billing test that does not pass one gets it from here.
 * The host keeps `Tests\Concerns\SeedsCurrencies` for its own tree.
 */
trait SeedsCurrencies
{
    protected function seedCurrencies(): void
    {
        $this->seed(CurrencySeeder::class);
    }
}
