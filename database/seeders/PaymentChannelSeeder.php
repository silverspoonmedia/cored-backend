<?php

namespace Database\Seeders;

use App\Domain\PaymentGateway\Enums\PaymentChannelKind;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentChannelSeeder extends Seeder
{
    public function run(): void
    {
        $midtrans = PaymentGateway::query()->where('name', 'midtrans')->first();
        $ipaymu = PaymentGateway::query()->where('name', 'ipaymu')->first();

        if ($midtrans === null || $ipaymu === null) {
            $this->command?->warn('PaymentChannelSeeder skipped: run PaymentGatewaySeeder first.');

            return;
        }

        $channels = [
            [
                'code' => 'qris',
                'display_name' => 'QRIS',
                'kind' => PaymentChannelKind::Qris,
                'sort_order' => 10,
                'routes' => [
                    ['gateway' => $ipaymu, 'type' => 'qris', 'channel' => 'qris', 'priority' => 10],
                    ['gateway' => $midtrans, 'type' => 'qris', 'channel' => null, 'priority' => 20],
                ],
            ],
            [
                'code' => 'va_bca',
                'display_name' => 'BCA Virtual Account',
                'kind' => PaymentChannelKind::Va,
                'sort_order' => 20,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'bank_transfer', 'channel' => 'bca', 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'va', 'channel' => 'bca', 'priority' => 20],
                ],
            ],
            [
                'code' => 'va_bni',
                'display_name' => 'BNI Virtual Account',
                'kind' => PaymentChannelKind::Va,
                'sort_order' => 30,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'bank_transfer', 'channel' => 'bni', 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'va', 'channel' => 'bni', 'priority' => 20],
                ],
            ],
            [
                'code' => 'va_mandiri',
                'display_name' => 'Mandiri Virtual Account',
                'kind' => PaymentChannelKind::Va,
                'sort_order' => 40,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'bank_transfer', 'channel' => 'mandiri', 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'va', 'channel' => 'mandiri', 'priority' => 20],
                ],
            ],
            [
                'code' => 'gopay',
                'display_name' => 'GoPay',
                'kind' => PaymentChannelKind::Ewallet,
                'sort_order' => 50,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'gopay', 'channel' => null, 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'qris', 'channel' => 'gopay', 'priority' => 20],
                ],
            ],
            [
                'code' => 'shopeepay',
                'display_name' => 'ShopeePay',
                'kind' => PaymentChannelKind::Ewallet,
                'sort_order' => 60,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'shopeepay', 'channel' => null, 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'qris', 'channel' => 'shopeepay', 'priority' => 20],
                ],
            ],
            [
                'code' => 'ovo',
                'display_name' => 'OVO',
                'kind' => PaymentChannelKind::Ewallet,
                'sort_order' => 70,
                'routes' => [
                    ['gateway' => $midtrans, 'type' => 'ovo', 'channel' => null, 'priority' => 10],
                    ['gateway' => $ipaymu, 'type' => 'qris', 'channel' => 'ovo', 'priority' => 20],
                ],
            ],
        ];

        foreach ($channels as $row) {
            $channel = PaymentChannel::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'display_name' => $row['display_name'],
                    'kind' => $row['kind'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );

            foreach ($row['routes'] as $route) {
                PaymentChannelRoute::query()->updateOrCreate(
                    [
                        'payment_channel_id' => $channel->id,
                        'payment_gateway_id' => $route['gateway']->id,
                    ],
                    [
                        'provider_payment_type' => $route['type'],
                        'provider_payment_channel' => $route['channel'],
                        'priority' => $route['priority'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
