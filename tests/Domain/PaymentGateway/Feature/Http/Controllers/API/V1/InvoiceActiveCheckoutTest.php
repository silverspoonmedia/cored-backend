<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Models\OrderDetail;
use App\Domain\Playground\Services\OrderService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class InvoiceActiveCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;
    use SetsUpPaymentChannels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPaymentGatewaysAndChannels();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_channel_checkout_cancels_prior_snap_session_without_external_transaction_id(): void
    {
        $invoice = $this->createPayableInvoice('midtrans', [
            'external_transaction_id' => null,
            'payment_channel_code' => null,
            'gateway_response_payload' => [
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://example.test/snap',
            ],
        ]);
        $admin = $this->userWithRole('Admin');

        $this->assertTrue(
            app(InvoiceActiveCheckoutCancellationService::class)->hasCheckoutSession($invoice->fresh()),
        );

        $this->mock(IpaymuPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(['ok' => false, 'message' => 'iPaymu down', 'http' => 502]);
        });

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('cancelTransaction')
                ->once()
                ->with(Mockery::type('string'))
                ->andReturn(['ok' => true, 'data' => [], 'message_key' => 'api/payments.midtrans.cancel_ok']);
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'data' => [
                        'qr_string' => 'midtrans-qris',
                        'actions' => [
                            ['name' => 'generate-qr-code', 'url' => 'https://example.test/qr.png'],
                        ],
                    ],
                    'message_key' => 'api/payments.midtrans.charge_created',
                    'http' => 201,
                ]);
        });

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertCreated();

        $invoice->refresh();
        $this->assertNull($invoice->gateway_response_payload);
        $this->assertSame('qris', $invoice->payment_channel_code);
    }

    public function test_abandon_active_checkout_clears_channel_session_and_calls_midtrans_cancel(): void
    {
        $invoice = $this->createPayableInvoice('midtrans', [
            'payment_channel_code' => 'qris',
            'external_transaction_id' => 'txn-channel-123',
            'gateway_response_payload' => ['transaction_id' => 'txn-channel-123'],
        ]);

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('cancelTransaction')
                ->once()
                ->with(Mockery::type('string'))
                ->andReturn(['ok' => true, 'data' => [], 'message_key' => 'api/payments.midtrans.cancel_ok']);
        });

        $this->partialMock(IpaymuPaymentService::class);

        app(InvoiceActiveCheckoutCancellationService::class)->abandonActiveCheckout($invoice);

        $invoice->refresh();
        $this->assertNull($invoice->external_transaction_id);
        $this->assertNull($invoice->payment_channel_code);
        $this->assertNull($invoice->gateway_response_payload);
        $this->assertNull($invoice->payment_gateway_id);
    }

    public function test_order_update_abandons_checkout_when_grand_total_changes(): void
    {
        $order = Order::factory()->pending()->create([
            'subtotal' => 240,
            'discount' => 10,
            'tax' => 5,
            'grand_total' => 235,
        ]);
        OrderDetail::factory()->create([
            'order_master_id' => $order->id,
            'product_name' => 'Widget',
            'quantity' => 2,
            'unit_price' => 120,
            'subtotal' => 240,
        ]);

        $invoice = $this->createPayableInvoice('midtrans', [
            'invoiceable_id' => $order->id,
            'invoiceable_type' => 'order',
            'customer_gross_total' => 235,
            'subtotal' => 235,
            'payment_channel_code' => 'qris',
            'external_transaction_id' => 'txn-order-edit-1',
        ]);

        $this->mock(InvoiceActiveCheckoutCancellationService::class, function ($mock): void {
            $mock->shouldReceive('hasCheckoutSession')->once()->andReturn(true);
            $mock->shouldReceive('abandonActiveCheckout')->once();
        });

        $updated = app(OrderService::class)->update($order->id, [
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'discount' => 0,
            'tax' => 0,
            'status' => 'pending',
            'details' => [
                ['product_name' => 'Big ticket', 'quantity' => 1, 'unit_price' => 500, 'discount' => 0],
            ],
        ]);

        $this->assertSame(500.0, (float) $updated->grand_total);
        $invoice->refresh();
        $this->assertSame(500.0, (float) $invoice->customer_gross_total);
    }

    public function test_channel_checkout_switches_ipaymu_va_channel_after_prior_session(): void
    {
        $invoice = $this->createPayableInvoice('ipaymu', [
            'payment_channel_code' => 'va_bni',
            'external_transaction_id' => '217546',
            'gateway_request_payload' => ['mode' => 'direct'],
            'gateway_response_payload' => [
                'Status' => 200,
                'Data' => [
                    'PaymentCode' => '8808123456',
                    'PaymentChannel' => 'BNI',
                ],
            ],
        ]);
        $admin = $this->userWithRole('Admin');

        $this->mock(IpaymuPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'data' => [
                        'Status' => 200,
                        'Data' => [
                            'TransactionId' => 217547,
                            'PaymentCode' => '3811800034254667',
                            'PaymentChannel' => 'MANDIRI',
                            'PaymentName' => 'Hallo Bali',
                        ],
                    ],
                    'message_key' => 'api/payments.ipaymu.charge_created',
                    'http' => 201,
                ]);
        });

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(['ok' => false, 'message' => 'Midtrans down', 'http' => 502]);
        });

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'va_mandiri'])
            ->assertCreated()
            ->assertJsonPath('data.channel_code', 'va_mandiri')
            ->assertJsonPath('data.instructions.va_number', '3811800034254667');

        $invoice->refresh();
        $this->assertSame('va_mandiri', $invoice->payment_channel_code);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
