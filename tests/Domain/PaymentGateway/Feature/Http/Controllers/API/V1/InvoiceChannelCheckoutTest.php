<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class InvoiceChannelCheckoutTest extends TestCase
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
        Mockery::close();

        parent::tearDown();
    }

    public function test_channel_checkout_assigns_gateway_when_invoice_has_none(): void
    {
        $invoice = $this->createPayableInvoice(null);
        $admin = $this->userWithRole('Admin');

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'data' => [
                        'qr_string' => 'midtrans-qris',
                    ],
                    'message_key' => 'api/payments.midtrans.charge_created',
                    'http' => 201,
                ]);
        });

        $this->partialMock(IpaymuPaymentService::class);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'midtrans');

        $invoice->refresh();
        $this->assertSame(
            PaymentGateway::query()->where('name', 'midtrans')->value('id'),
            $invoice->payment_gateway_id,
        );
        $this->assertNotNull($invoice->payment_channel_route_id);
    }

    public function test_channel_checkout_routes_qris_to_midtrans_and_returns_instructions(): void
    {
        $invoice = $this->createPayableInvoice('midtrans');
        $admin = $this->userWithRole('Admin');

        $this->mock(MidtransPaymentService::class, function ($mock): void {
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

        $this->partialMock(IpaymuPaymentService::class);

        $response = $this->actingAsJwt($admin)->postJson(
            '/api/v1/invoices/'.$invoice->id.'/channel-checkout',
            ['channel_code' => 'qris'],
        );

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.channel_code', 'qris')
            ->assertJsonPath('data.gateway', 'midtrans')
            ->assertJsonPath('data.instructions.qr_string', 'midtrans-qris')
            ->assertJsonPath('data.payment_channel_fee', 700)
            ->assertJsonPath('data.customer_gross_total', 100700);

        $invoice->refresh();
        $this->assertSame(700.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(100700.0, (float) $invoice->customer_gross_total);
        $this->assertSame('qris', $invoice->payment_channel_code);
        $this->assertSame(
            PaymentGateway::query()->where('name', 'midtrans')->value('id'),
            $invoice->payment_gateway_id,
        );
    }

    public function test_channel_checkout_fails_over_to_second_gateway_route(): void
    {
        $invoice = $this->createPayableInvoice('midtrans');
        $admin = $this->userWithRole('Admin');

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'ok' => false,
                    'message' => 'Midtrans down',
                    'http' => 502,
                ]);
        });

        $this->mock(IpaymuPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'data' => [
                        'Status' => 200,
                        'Data' => [
                            'TransactionId' => 12345,
                            'QrString' => 'ipaymu-qris',
                        ],
                    ],
                    'message_key' => 'api/payments.ipaymu.charge_created',
                    'http' => 201,
                ]);
        });

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'ipaymu')
            ->assertJsonPath('data.instructions.qr_string', 'ipaymu-qris');
    }

    public function test_channel_checkout_returns_snap_fallback_when_all_routes_fail(): void
    {
        $invoice = $this->createPayableInvoice('midtrans');
        $admin = $this->userWithRole('Admin');

        $this->mock(MidtransPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(['ok' => false, 'message' => 'Midtrans down', 'http' => 502]);
        });

        $this->mock(IpaymuPaymentService::class, function ($mock): void {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn(['ok' => false, 'message' => 'iPaymu down', 'http' => 502]);
        });

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data.snap_fallback_available', true)
            ->assertJsonPath('data.failed_routes', 2);
    }

    public function test_channel_checkout_rejects_inactive_channel(): void
    {
        PaymentChannel::query()->where('code', 'qris')->update(['is_active' => false]);

        $invoice = $this->createPayableInvoice();
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_channel_checkout_requires_initiate_payment_permission(): void
    {
        $invoice = $this->createPayableInvoice();
        $user = $this->userWithRole('Management');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertForbidden();
    }

    public function test_channel_checkout_service_purchase_without_buyer_body_succeeds(): void
    {
        $owner = User::factory()->create([
            'name' => 'Purchase Owner',
            'email' => 'owner@example.com',
        ]);
        $purchase = \App\Domain\Service\Models\ServicePurchase::factory()->pending()->create([
            'user_id' => $owner->id,
        ]);
        $invoice = $this->createPayableInvoice(null, [
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
        ]);
        $admin = $this->userWithRole('Admin');

        $this->mock(MidtransPaymentService::class, function ($mock) use ($invoice): void {
            $mock->shouldReceive('charge')
                ->once()
                ->withArgs(function (array $input) use ($invoice): bool {
                    return ($input['invoice_id'] ?? null) === $invoice->id;
                })
                ->andReturn([
                    'ok' => true,
                    'data' => [
                        'qr_string' => 'qris-no-buyer',
                    ],
                    'message_key' => 'api/payments.midtrans.charge_created',
                    'http' => 201,
                ]);
        });

        $this->partialMock(IpaymuPaymentService::class);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', ['channel_code' => 'qris'])
            ->assertCreated()
            ->assertJsonPath('status', 'success');
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
