<?php

namespace Tests\Domain\Core\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IpaymuReturnWebTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = 'http://spa.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.spa.url' => self::SPA]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_return_redirects_to_spa_payment_return_and_attempts_sync(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $invoice = $this->createIpaymuInvoice($purchase, $gateway, [
            'external_transaction_id' => '217387',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldReceive('syncInvoiceStatus')
            ->once()
            ->with(Mockery::on(fn (Invoice $i) => $i->id === $invoice->id))
            ->andReturn([
                'ok' => true,
                'data' => ['invoice' => ['status' => 'paid']],
                'message_key' => 'api/payments.ipaymu.sync_ok',
            ]);
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $response = $this->get('/payment/ipaymu/return?trx_id=217387&status=berhasil');

        $response->assertRedirect($this->spaReturnUrl('/payment/return', [
            'invoice_id' => $invoice->id,
            'type' => 'service_purchase',
            'reference_id' => $purchase->id,
            'trx_id' => '217387',
        ]));
    }

    public function test_return_honours_explicit_service_purchase_redirect_query(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $invoice = $this->createIpaymuInvoice($purchase, $gateway, [
            'external_transaction_id' => '999001',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldReceive('syncInvoiceStatus')->once()->andReturn([
            'ok' => true,
            'data' => ['invoice' => ['status' => 'pending']],
            'message_key' => 'api/payments.ipaymu.sync_ok',
        ]);
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $response = $this->get('/payment/ipaymu/return?'.http_build_query([
            'redirect' => 'service_purchase',
            'purchase' => $purchase->id,
            'invoice_id' => $invoice->id,
            'trx_id' => '999001',
        ]));

        $response->assertRedirect($this->spaReturnUrl('/payment/return', [
            'invoice_id' => $invoice->id,
            'type' => 'service_purchase',
            'reference_id' => $purchase->id,
            'trx_id' => '999001',
        ]));
    }

    public function test_cancel_redirects_to_spa_payment_cancel_without_sync(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $invoice = $this->createIpaymuInvoice($purchase, $gateway, [
            'external_transaction_id' => '888001',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldNotReceive('syncInvoiceStatus');
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $response = $this->get('/payment/ipaymu/cancel?trx_id=888001');

        $response->assertRedirect($this->spaReturnUrl('/payment/cancel', [
            'invoice_id' => $invoice->id,
            'type' => 'service_purchase',
            'reference_id' => $purchase->id,
            'trx_id' => '888001',
        ]));
    }

    public function test_return_without_matching_invoice_redirects_to_spa_payment_return_without_ids(): void
    {
        $response = $this->get('/payment/ipaymu/return?trx_id=000000');

        $response->assertRedirect(self::SPA.'/payment/return?trx_id=000000');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function spaReturnUrl(string $path, array $query): string
    {
        // The controller preserves the resolved invoice id + type first; match
        // http_build_query ordering by building from the same insertion order.
        return self::SPA.$path.'?'.http_build_query($query);
    }

    private function ipaymuGateway(): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'name' => 'ipaymu',
            'display_name' => 'iPaymu',
            'is_active' => true,
            'priority' => 20,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createIpaymuInvoice(ServicePurchase $purchase, PaymentGateway $gateway, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => $purchase->settlement_amount,
            'payment_channel_fee' => 0,
            'customer_gross_total' => $purchase->settlement_amount,
            'currency' => 'IDR',
            'status' => 'pending',
        ], $overrides));
    }
}
