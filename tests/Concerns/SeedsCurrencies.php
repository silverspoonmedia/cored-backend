<?php

namespace Tests\Concerns;

use App\Domain\Currency\Database\Seeders\CurrencySeeder;

trait SeedsCurrencies
{
    protected function seedCurrencies(): void
    {
        $this->seed(CurrencySeeder::class);
    }
}
