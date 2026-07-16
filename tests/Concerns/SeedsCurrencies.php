<?php

namespace Tests\Concerns;

use Database\Seeders\CurrencySeeder;

trait SeedsCurrencies
{
    protected function seedCurrencies(): void
    {
        $this->seed(CurrencySeeder::class);
    }
}
