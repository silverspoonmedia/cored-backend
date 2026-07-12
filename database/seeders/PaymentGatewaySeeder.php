<?php

namespace Database\Seeders;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'midtrans',
                'display_name' => 'Midtrans',
                'is_active' => true,
                'priority' => 10,
            ],
            [
                'name' => 'ipaymu',
                'display_name' => 'iPaymu',
                'is_active' => true,
                'priority' => 20,
            ],
        ];

        foreach ($rows as $row) {
            PaymentGateway::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'display_name' => $row['display_name'],
                    'is_active' => $row['is_active'],
                    'priority' => $row['priority'],
                ],
            );
        }
    }
}
