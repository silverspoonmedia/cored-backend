<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCheckoutWebTest extends TestCase
{
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

        $admin = $this->actingWithApiGuard(User::factory()->create());

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

        $admin = $this->actingWithApiGuard(User::factory()->create());

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

        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->postJson("/invoices/{$invoiceId}/checkout/snap-token")
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    private function actingWithApiGuard(User $user): static
    {
        return $this->actingAs($user, 'api');
    }
}
