<?php

namespace Tests\Domain\PaymentGateway\Feature;

use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransAccountLinkingWebhookService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentNotificationService;
use App\Support\ApiResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URI = '/api/v1/payments/providers/midtrans/webhook';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_returns_ping(): void
    {
        $this->getJson(self::WEBHOOK_URI)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/payments.midtrans.webhook_ping'),
            ]);
    }

    public function test_post_invalid_json_returns_400(): void
    {
        $this->call(
            'POST',
            self::WEBHOOK_URI,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{not-json',
        )
            ->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => __('api/payments.midtrans.webhook_invalid_json'),
            ]);
    }

    public function test_post_subscription_notification_returns_ack(): void
    {
        $this->postJson(self::WEBHOOK_URI, [
            'event_name' => 'subscription.create',
            'subscription' => ['id' => 'sub-123'],
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/payments.midtrans.webhook_subscription_ack'),
            ]);
    }

    public function test_post_payment_notification_delegates_to_signature_service(): void
    {
        $payload = [
            'order_id' => 'INV-20260710-WEB01',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => 'deadbeef',
        ];

        $mock = Mockery::mock(MidtransPaymentNotificationService::class);
        $mock->shouldReceive('handle')
            ->once()
            ->with($payload)
            ->andReturn(ApiResponder::success(null, __('api/payments.midtrans.webhook_ok')));

        $this->app->instance(MidtransPaymentNotificationService::class, $mock);

        $this->postJson(self::WEBHOOK_URI, $payload)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/payments.midtrans.webhook_ok'),
            ]);
    }

    public function test_post_account_linking_delegates_to_account_linking_service(): void
    {
        $payload = [
            'additionalInfo' => [
                'accountStatus' => 'LINKED',
                'paymentType' => 'gopay',
            ],
        ];

        $mock = Mockery::mock(MidtransAccountLinkingWebhookService::class);
        $mock->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(\Illuminate\Http\Request::class))
            ->andReturn(ApiResponder::success(null, 'account-linking-ack'));

        $this->app->instance(MidtransAccountLinkingWebhookService::class, $mock);

        $this->postJson(self::WEBHOOK_URI, $payload)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'account-linking-ack',
            ]);
    }

    public function test_post_payment_notification_returns_invalid_signature_from_service(): void
    {
        $payload = [
            'order_id' => 'INV-20260710-BADSIG',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => 'invalid',
        ];

        /** @var MidtransPaymentNotificationService&MockInterface $mock */
        $mock = Mockery::mock(MidtransPaymentNotificationService::class);
        $mock->shouldReceive('handle')
            ->once()
            ->with($payload)
            ->andReturn(ApiResponder::error(__('api/payments.midtrans.webhook_invalid_signature'), 401));

        $this->app->instance(MidtransPaymentNotificationService::class, $mock);

        $this->postJson(self::WEBHOOK_URI, $payload)
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/payments.midtrans.webhook_invalid_signature'),
            ]);
    }
}
