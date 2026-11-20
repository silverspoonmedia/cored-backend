<?php

namespace Tests\Concerns;

use App\Domain\Currency\Database\Seeders\CurrencySeeder;

/**
 * Host's `seedCurrencies()`: the billing catalog with no argument.
 */
trait SeedsCurrencies
{
    protected function seedCurrencies(): void
    {
        $this->seed(CurrencySeeder::class);
    }
}
