<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureGatewayRequestTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URI = '/api/v1/payments/providers/midtrans/webhook';

    public function test_enforced_request_without_secret_returns_403(): void
    {
        config([
            'gateway.enforce' => true,
            'gateway.secret' => 'testing-gateway-secret',
            'gateway.allowed_ips' => [],
        ]);

        $this->getJson(self::WEBHOOK_URI)
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => 'Forbidden.',
            ]);
    }

    public function test_enforced_request_with_valid_secret_allows_access(): void
    {
        config([
            'gateway.enforce' => true,
            'gateway.secret' => 'testing-gateway-secret',
            'gateway.allowed_ips' => [],
        ]);

        $this->withHeader('X-Gateway-Secret', 'testing-gateway-secret')
            ->getJson(self::WEBHOOK_URI)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/payments.midtrans.webhook_ping'),
            ]);
    }

    public function test_disabled_enforcement_allows_request_without_secret(): void
    {
        config([
            'gateway.enforce' => false,
            'gateway.secret' => 'testing-gateway-secret',
            'gateway.allowed_ips' => [],
        ]);

        $this->getJson(self::WEBHOOK_URI)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/payments.midtrans.webhook_ping'),
            ]);
    }
}
