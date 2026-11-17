<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService;
use App\Domain\PaymentGateway\Services\Payment\InvoicePaymentInitiationGuard;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuInvoiceSynchronizer;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use App\Domain\Service\Services\ServiceFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvoicePaymentInitiationGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_midtrans_syncs_settlement_and_rejects_payment(): void
    {
        $invoice = $this->createInvoice('midtrans', ['status' => 'pending']);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchMidtransTransactionStatus')
            ->once()
            ->with($invoice->invoice_number)
            ->andReturn((object) [
                'transaction_status' => 'settlement',
                'gross_amount' => '100000.00',
                'transaction_id' => 'MTX-SETTLED',
                'payment_type' => 'bank_transfer',
            ]);

        try {
            $guard->ensurePayableForMidtrans($invoice->id);
            $this->fail('Expected invoice_not_payable abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_midtrans_transaction_not_found_allows_payable_invoice(): void
    {
        $invoice = $this->createInvoice('midtrans', ['status' => 'pending']);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchMidtransTransactionStatus')
            ->once()
            ->andThrow(new \Exception('Midtrans API is returning API error. HTTP status code: 404, API response: Transaction doesn\'t exist.'));

        $result = $guard->ensurePayableForMidtrans($invoice->id);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertSame('pending', $result->status);
    }

    public function test_midtrans_external_sync_failure_returns_503(): void
    {
        $invoice = $this->createInvoice('midtrans', ['status' => 'pending']);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchMidtransTransactionStatus')
            ->once()
            ->andThrow(new \Exception('Gateway timeout'));

        $result = $guard->ensurePayableForMidtrans($invoice->id);

        $this->assertIsArray($result);
        $this->assertFalse($result['ok']);
        $this->assertSame(503, $result['http']);
        $this->assertStringContainsString(__('api/payments.midtrans.external_sync_failed'), $result['message']);
    }

    public function test_ipaymu_syncs_success_and_rejects_payment(): void
    {
        $invoice = $this->createInvoice('ipaymu', [
            'status' => 'pending',
            'external_transaction_id' => '90001',
        ]);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchIpaymuTransactionStatus')
            ->once()
            ->andReturn([
                'Status' => 200,
                'Data' => [
                    'Status' => 1,
                    'Amount' => 100000,
                    'TransactionId' => 90001,
                    'PaymentChannel' => 'qris',
                ],
            ]);

        try {
            $guard->ensurePayableForIpaymu($invoice->id, rejectActiveCheckout: true);
            $this->fail('Expected invoice_not_payable abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_ipaymu_without_external_id_skips_external_sync(): void
    {
        $invoice = $this->createInvoice('ipaymu', [
            'status' => 'pending',
            'external_transaction_id' => null,
        ]);

        $guard = $this->partialGuard();
        $guard->shouldNotReceive('fetchIpaymuTransactionStatus');

        $result = $guard->ensurePayableForIpaymu($invoice->id, rejectActiveCheckout: true);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertSame('pending', $result->status);
    }

    public function test_ipaymu_active_pending_checkout_returns_422(): void
    {
        $invoice = $this->createInvoice('ipaymu', [
            'status' => 'pending',
            'external_transaction_id' => '90002',
        ]);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchIpaymuTransactionStatus')
            ->once()
            ->andReturn([
                'Status' => 200,
                'Data' => [
                    'Status' => 0,
                    'Amount' => 100000,
                    'TransactionId' => 90002,
                ],
            ]);

        try {
            $guard->ensurePayableForIpaymu($invoice->id, rejectActiveCheckout: true);
            $this->fail('Expected active_payment_exists abort.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(__('api/payments.ipaymu.active_payment_exists'), $e->getMessage());
        }

        $invoice->refresh();
        $this->assertSame('90002', $invoice->external_transaction_id);
    }

    public function test_ipaymu_external_sync_failure_returns_503(): void
    {
        $invoice = $this->createInvoice('ipaymu', [
            'status' => 'pending',
            'external_transaction_id' => '90003',
        ]);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchIpaymuTransactionStatus')
            ->once()
            ->andThrow(new \RuntimeException('iPaymu unavailable'));

        $result = $guard->ensurePayableForIpaymu($invoice->id, rejectActiveCheckout: true);

        $this->assertIsArray($result);
        $this->assertFalse($result['ok']);
        $this->assertSame(503, $result['http']);
    }

    public function test_second_midtrans_initiation_fails_after_first_sync_marks_paid(): void
    {
        $invoice = $this->createInvoice('midtrans', ['status' => 'pending']);

        $guard = $this->partialGuard();
        $guard->shouldReceive('fetchMidtransTransactionStatus')
            ->once()
            ->andReturn((object) [
                'transaction_status' => 'settlement',
                'gross_amount' => '100000.00',
                'transaction_id' => 'MTX-SETTLED-2',
                'payment_type' => 'bank_transfer',
            ]);

        try {
            $guard->ensurePayableForMidtrans($invoice->id);
            $this->fail('First call should reject after sync to paid.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        try {
            $guard->ensurePayableForMidtrans($invoice->id);
            $this->fail('Second call should also reject.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    private function partialGuard(): InvoicePaymentInitiationGuard&MockInterface
    {
        return Mockery::mock(InvoicePaymentInitiationGuard::class, [
            app(InvoiceRepositoryInterface::class),
            new MidtransInvoiceSynchronizer(
                app(InvoiceFundsLedgerService::class),
                app(ServiceFulfillmentService::class),
                app(PaymentRealtime::class),
            ),
            new IpaymuInvoiceSynchronizer(
                app(InvoiceFundsLedgerService::class),
                app(ServiceFulfillmentService::class),
                app(PaymentRealtime::class),
            ),
        ])->shouldAllowMockingProtectedMethods()->makePartial();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(string $gatewayName, array $overrides = []): Invoice
    {
        $this->seedPaymentGatewayConfig();

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'midtrans'],
            ['display_name' => 'Midtrans', 'is_active' => true, 'priority' => 10],
        );
        PaymentGateway::query()->updateOrCreate(
            ['name' => 'ipaymu'],
            ['display_name' => 'iPaymu', 'is_active' => true, 'priority' => 20],
        );

        $gateway = PaymentGateway::query()->where('name', $gatewayName)->firstOrFail();

        return Invoice::query()->create(array_merge([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'bank_transfer',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ], $overrides));
    }

    private function seedPaymentGatewayConfig(): void
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
}
