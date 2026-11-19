<?php

namespace App\Domain\PaymentGateway\Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\SeedsRolesAndPermissions;
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

        $this->partialMock(IpaymuPaymentService::class);

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

        $this->partialMock(MidtransPaymentService::class);

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
