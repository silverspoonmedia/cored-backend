<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_return_redirects_to_service_purchase_and_attempts_sync(): void
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

        $this->get('/payment/ipaymu/return?trx_id=217387&status=berhasil')
            ->assertRedirect(route('service-purchases.show', $purchase->id))
            ->assertSessionHas('info');
    }

    public function test_return_honours_explicit_service_purchase_redirect_query(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $this->createIpaymuInvoice($purchase, $gateway, [
            'external_transaction_id' => '999001',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldReceive('syncInvoiceStatus')->once()->andReturn([
            'ok' => true,
            'data' => ['invoice' => ['status' => 'pending']],
            'message_key' => 'api/payments.ipaymu.sync_ok',
        ]);
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $url = route('payment.ipaymu.return', [
            'redirect' => 'service_purchase',
            'purchase' => $purchase->id,
            'invoice_id' => Invoice::query()->first()->id,
            'trx_id' => '999001',
        ]);

        $this->get($url)
            ->assertRedirect(route('service-purchases.show', $purchase->id))
            ->assertSessionHas('info');
    }

    public function test_cancel_redirects_without_sync(): void
    {
        $purchase = ServicePurchase::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $this->createIpaymuInvoice($purchase, $gateway, [
            'external_transaction_id' => '888001',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldNotReceive('syncInvoiceStatus');
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $this->get('/payment/ipaymu/cancel?trx_id=888001')
            ->assertRedirect(route('service-purchases.show', $purchase->id))
            ->assertSessionHas('warning');
    }

    public function test_return_without_matching_invoice_redirects_to_invoice_index(): void
    {
        $this->get('/payment/ipaymu/return?trx_id=000000')
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHas('warning');
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
