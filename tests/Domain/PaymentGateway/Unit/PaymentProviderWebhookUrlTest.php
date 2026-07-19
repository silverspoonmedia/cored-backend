<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuNotifyUrl;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransNotifyUrl;
use App\Domain\PaymentGateway\Services\Payment\PaymentProviderWebhookUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderWebhookUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_uses_env_override_when_set(): void
    {
        config()->set('payment.gateways.midtrans.notify_url', 'https://api.example/api/v1/payments/providers/midtrans/webhook');

        $this->assertSame(
            'https://api.example/api/v1/payments/providers/midtrans/webhook',
            MidtransNotifyUrl::webhookUrl(),
        );
    }

    public function test_ipaymu_uses_env_override_when_set(): void
    {
        config()->set('payment.gateways.ipaymu.notify_url', 'https://api.example/api/v1/payments/providers/ipaymu/webhook');

        $this->assertSame(
            'https://api.example/api/v1/payments/providers/ipaymu/webhook',
            IpaymuNotifyUrl::webhookUrl(),
        );
    }

    public function test_resolve_falls_back_to_named_route(): void
    {
        $url = PaymentProviderWebhookUrl::resolve('midtrans');

        $this->assertStringContainsString('/api/v1/payments/providers/midtrans/webhook', $url);
    }
}
