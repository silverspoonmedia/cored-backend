<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransChargeParameterBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class MidtransChargeParameterBuilderTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPaymentChannels;

    private MidtransChargeParameterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPaymentGatewaysAndChannels();
        $this->builder = app(MidtransChargeParameterBuilder::class);
    }

    public function test_builds_qris_charge(): void
    {
        $route = $this->midtransRoute('qris', 'qris', null);
        $invoice = $this->createPayableInvoice();

        $charge = $this->builder->buildFromRoute($route, $invoice);

        $this->assertSame('qris', $charge['payment_type']);
        $this->assertArrayHasKey('qris', $charge);
    }

    public function test_builds_va_charge(): void
    {
        $route = $this->midtransRoute('va_bca', 'bank_transfer', 'bca');
        $invoice = $this->createPayableInvoice();

        $charge = $this->builder->buildFromRoute($route, $invoice);

        $this->assertSame('bank_transfer', $charge['payment_type']);
        $this->assertSame('bca', $charge['bank_transfer']['bank']);
    }

    public function test_builds_gopay_charge(): void
    {
        $route = $this->midtransRoute('gopay', 'gopay', null);
        $invoice = $this->createPayableInvoice();

        $charge = $this->builder->buildFromRoute($route, $invoice);

        $this->assertSame('gopay', $charge['payment_type']);
        $this->assertTrue($charge['gopay']['enable_callback']);
    }

    private function midtransRoute(string $channelCode, string $type, ?string $channel): PaymentChannelRoute
    {
        return PaymentChannelRoute::query()
            ->whereHas('paymentChannel', fn ($q) => $q->where('code', $channelCode))
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'midtrans'))
            ->firstOrFail()
            ->fill([
                'provider_payment_type' => $type,
                'provider_payment_channel' => $channel,
            ]);
    }
}
