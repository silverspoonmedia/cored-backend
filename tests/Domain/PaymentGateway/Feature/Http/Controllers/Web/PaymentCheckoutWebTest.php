<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithWebAuth;
use Tests\TestCase;

class PaymentCheckoutWebTest extends TestCase
{
    use InteractsWithWebAuth;
    use RefreshDatabase;

    public function test_snap_token_proxies_to_matching_provider_api(): void
    {
        $invoiceId = fake()->uuid();

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $invoiceId,
                    'payment_gateway' => ['name' => 'midtrans'],
                ],
            ], 200),
            '*/api/v1/payments/providers/midtrans/snap/token' => Http::response([
                'status' => 'success',
                'message' => 'Snap transaction created.',
                'data' => [
                    'snap_token' => 'snap-abc',
                    'client_key' => 'SB-client',
                    'invoice_id' => $invoiceId,
                ],
            ], 201),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $response = $admin->postJson("/invoices/{$invoiceId}/checkout/snap-token");

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('provider', 'midtrans')
            ->assertJsonPath('data.snap_token', 'snap-abc');

        Http::assertSent(function ($request) use ($invoiceId) {
            return str_contains($request->url(), '/api/v1/payments/providers/midtrans/snap/token')
                && ($request->data()['invoice_id'] ?? null) === $invoiceId;
        });
    }

    public function test_snap_token_forwards_ipaymu_return_urls(): void
    {
        $invoiceId = fake()->uuid();
        $returnUrl = 'http://monolith.test/payment/ipaymu/return?invoice_id='.$invoiceId;
        $cancelUrl = 'http://monolith.test/payment/ipaymu/cancel?invoice_id='.$invoiceId;

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $invoiceId,
                    'payment_gateway' => ['name' => 'ipaymu'],
                ],
            ], 200),
            '*/api/v1/payments/providers/ipaymu/snap/token' => Http::response([
                'status' => 'success',
                'data' => [
                    'redirect_url' => 'https://sandbox.ipaymu.com/payment',
                    'invoice_id' => $invoiceId,
                ],
            ], 201),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $admin->postJson("/invoices/{$invoiceId}/checkout/snap-token", [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ])
            ->assertCreated()
            ->assertJsonPath('provider', 'ipaymu');

        Http::assertSent(function ($request) use ($invoiceId, $returnUrl, $cancelUrl) {
            $data = $request->data();

            return str_contains($request->url(), '/api/v1/payments/providers/ipaymu/snap/token')
                && ($data['invoice_id'] ?? null) === $invoiceId
                && ($data['return_url'] ?? null) === $returnUrl
                && ($data['cancel_url'] ?? null) === $cancelUrl;
        });
    }

    public function test_snap_token_rejects_unsupported_gateway(): void
    {
        $invoiceId = fake()->uuid();

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $invoiceId,
                    'payment_gateway' => ['name' => 'stripe'],
                ],
            ], 200),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $admin->postJson("/invoices/{$invoiceId}/checkout/snap-token")
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_channel_checkout_proxies_to_api(): void
    {
        $invoiceId = fake()->uuid();

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}/channel-checkout" => Http::response([
                'status' => 'success',
                'data' => [
                    'channel_code' => 'qris',
                    'instructions' => ['type' => 'qris', 'qr_string' => 'abc'],
                ],
            ], 201),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $admin->postJson("/invoices/{$invoiceId}/checkout/channel", ['channel_code' => 'qris'])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.instructions.qr_string', 'abc');

        Http::assertSent(function ($request) use ($invoiceId) {
            return str_contains($request->url(), "/api/v1/invoices/{$invoiceId}/channel-checkout")
                && ($request->data()['channel_code'] ?? null) === 'qris';
        });
    }

    public function test_order_show_prefetches_payment_channels_from_versioned_api(): void
    {
        $orderId = fake()->uuid();
        $invoiceId = fake()->uuid();

        Http::fake([
            "*/api/playground/orders/{$orderId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $orderId,
                    'order_number' => 'ORD-001',
                    'status' => 'pending',
                    'invoice' => [
                        'id' => $invoiceId,
                        'status' => 'pending',
                        'currency' => 'IDR',
                        'customer_gross_total' => 100000,
                        'payment_gateway' => ['name' => 'midtrans', 'display_name' => 'Midtrans'],
                    ],
                ],
            ], 200),
            '*/api/v1/payment-channels' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'code' => 'qris',
                        'display_name' => 'QRIS',
                        'category' => 'qris',
                        'is_active' => true,
                    ],
                ],
            ], 200),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $admin->get("/orders/{$orderId}")
            ->assertOk()
            ->assertViewHas('checkoutMode', 'channel')
            ->assertViewHas('paymentChannels', fn (array $channels): bool => ($channels[0]['code'] ?? null) === 'qris');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/v1/payment-channels');
        });

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '/api/playground/payment-channels');
        });
    }

    public function test_sync_status_proxies_to_matching_provider_api(): void
    {
        $invoiceId = fake()->uuid();

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $invoiceId,
                    'payment_gateway' => ['name' => 'ipaymu'],
                ],
            ], 200),
            "*/api/v1/payments/providers/ipaymu/invoices/{$invoiceId}/sync-status" => Http::response([
                'status' => 'success',
                'data' => [
                    'invoice' => ['id' => $invoiceId, 'status' => 'pending'],
                ],
            ], 200),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create());

        $admin->postJson("/invoices/{$invoiceId}/checkout/sync-status")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Http::assertSent(function ($request) use ($invoiceId) {
            return str_contains($request->url(), "/api/v1/payments/providers/ipaymu/invoices/{$invoiceId}/sync-status");
        });
    }

}
