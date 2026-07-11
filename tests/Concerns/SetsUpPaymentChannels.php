<?php

namespace Tests\Concerns;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Database\Seeders\PaymentChannelSeeder;
use Database\Seeders\PaymentGatewaySeeder;
use Illuminate\Support\Str;

trait SetsUpPaymentChannels
{
    protected function seedPaymentGatewaysAndChannels(): void
    {
        $this->seedPaymentGatewayConfig();
        $this->seed(PaymentGatewaySeeder::class);
        $this->seed(PaymentChannelSeeder::class);
    }

    protected function seedPaymentGatewayConfig(): void
    {
        config([
            'payment.gateways.midtrans' => [
                'server_key' => 'SB-Mid-server-test',
                'client_key' => 'SB-Mid-client-test',
                'is_production' => false,
            ],
            'payment.gateways.ipaymu' => [
                'va' => '1179000899',
                'api_key' => 'test-api-key',
                'is_production' => false,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPayableInvoice(string $gatewayName = 'midtrans', array $overrides = []): Invoice
    {
        $gateway = PaymentGateway::query()->where('name', $gatewayName)->firstOrFail();

        return Invoice::query()->create(array_merge([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'order',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'payment_channel_code' => null,
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ], $overrides));
    }
}
