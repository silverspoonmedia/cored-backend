<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\Playground\Models\Order;
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

    public function test_return_redirects_to_order_and_attempts_sync(): void
    {
        $order = Order::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $invoice = $this->createIpaymuInvoice($order, $gateway, [
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
            ->assertRedirect(route('orders.show', $order->id))
            ->assertSessionHas('info');
    }

    public function test_return_honours_explicit_order_redirect_query(): void
    {
        $order = Order::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $this->createIpaymuInvoice($order, $gateway, [
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
            'redirect' => 'order',
            'order' => $order->id,
            'invoice_id' => Invoice::query()->first()->id,
            'trx_id' => '999001',
        ]);

        $this->get($url)
            ->assertRedirect(route('orders.show', $order->id))
            ->assertSessionHas('info');
    }

    public function test_cancel_redirects_without_sync(): void
    {
        $order = Order::factory()->pending()->create();
        $gateway = $this->ipaymuGateway();
        $this->createIpaymuInvoice($order, $gateway, [
            'external_transaction_id' => '888001',
        ]);

        $payments = Mockery::mock(IpaymuPaymentService::class);
        $payments->shouldNotReceive('syncInvoiceStatus');
        $this->app->instance(IpaymuPaymentService::class, $payments);

        $this->get('/payment/ipaymu/cancel?trx_id=888001')
            ->assertRedirect(route('orders.show', $order->id))
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
    private function createIpaymuInvoice(Order $order, PaymentGateway $gateway, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoiceable_id' => $order->id,
            'invoiceable_type' => 'order',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => $order->grand_total,
            'payment_channel_fee' => 0,
            'customer_gross_total' => $order->grand_total,
            'currency' => 'IDR',
            'status' => 'pending',
        ], $overrides));
    }
}
