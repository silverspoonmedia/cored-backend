<?php

namespace App\Domain\Currency\Database\Seeders;

use App\Domain\Currency\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimal_places' => 0, 'is_active' => true, 'is_settlement' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'is_settlement' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true, 'is_settlement' => false],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0, 'is_active' => true, 'is_settlement' => false],
        ];

        foreach ($rows as $row) {
            Currency::query()->updateOrCreate(
                ['code' => $row['code']],
                $row,
            );
        }
    }
}
