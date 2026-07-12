<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutInstructionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class InvoiceCheckoutInstructionResolverTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPaymentChannels;

    private InvoiceCheckoutInstructionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPaymentGatewaysAndChannels();
        $this->resolver = app(InvoiceCheckoutInstructionResolver::class);
    }

    public function test_resolves_persisted_ipaymu_va_checkout_session(): void
    {
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');
        $invoice->payment_gateway_id = $gateway->id;
        $invoice->payment_channel_code = 'va_bca';
        $invoice->external_transaction_id = '217546';
        $invoice->gateway_request_payload = ['mode' => 'direct'];
        $invoice->gateway_response_payload = [
            'Status' => 200,
            'Data' => [
                'PaymentCode' => '3811800034254667',
                'PaymentChannel' => 'BCA',
                'PaymentName' => 'Hallo Bali',
            ],
        ];
        $invoice->save();
        $invoice->load('paymentGateway');

        $session = $this->resolver->resolve($invoice);

        $this->assertNotNull($session);
        $this->assertSame('va_bca', $session['channel_code']);
        $this->assertSame('3811800034254667', $session['instructions']['va_number']);
    }

    public function test_returns_null_for_snap_redirect_payload(): void
    {
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');
        $invoice->payment_gateway_id = $gateway->id;
        $invoice->payment_channel_code = 'va_bca';
        $invoice->gateway_request_payload = ['mode' => 'redirect'];
        $invoice->gateway_response_payload = ['Status' => 200, 'Data' => ['Url' => 'https://example.test/pay']];
        $invoice->save();
        $invoice->load('paymentGateway');

        $this->assertNull($this->resolver->resolve($invoice));
    }

    public function test_returns_null_when_instructions_are_empty(): void
    {
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');
        $invoice->payment_gateway_id = $gateway->id;
        $invoice->payment_channel_code = 'va_bca';
        $invoice->gateway_request_payload = ['mode' => 'direct'];
        $invoice->gateway_response_payload = ['Status' => 200, 'Data' => []];
        $invoice->save();
        $invoice->load('paymentGateway');

        $this->assertNull($this->resolver->resolve($invoice));
    }
}
