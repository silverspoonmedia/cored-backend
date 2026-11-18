<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\PaymentInstructionNormalizer;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInstructionNormalizerTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPaymentChannels;

    private PaymentInstructionNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPaymentGatewaysAndChannels();
        $this->normalizer = app(PaymentInstructionNormalizer::class);
    }

    public function test_normalizes_midtrans_qris_response(): void
    {
        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'midtrans')->firstOrFail();
        $invoice = $this->createPayableInvoice('midtrans');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'qr_string' => '000201010212',
            'actions' => [
                ['name' => 'generate-qr-code', 'url' => 'https://api.midtrans.com/qr/abc'],
            ],
            'transaction_id' => 'MTX-1',
        ]);

        $this->assertSame('qris', $result['channel_code']);
        $this->assertSame('midtrans', $result['gateway']);
        $this->assertSame('qris', $result['instructions']['type']);
        $this->assertSame('000201010212', $result['instructions']['qr_string']);
        $this->assertSame('https://api.midtrans.com/qr/abc', $result['instructions']['qr_url']);
    }

    public function test_normalizes_ipaymu_va_response(): void
    {
        $channel = PaymentChannel::query()->where('code', 'va_bni')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'Status' => 200,
            'Data' => [
                'PaymentNo' => '8808123456',
                'PaymentChannel' => 'bni',
                'PaymentName' => 'John Doe',
            ],
        ]);

        $this->assertSame('va', $result['instructions']['type']);
        $this->assertSame('8808123456', $result['instructions']['va_number']);
        $this->assertSame('bni', $result['instructions']['bank_name']);
    }

    public function test_normalizes_ipaymu_va_payment_code_response(): void
    {
        $channel = PaymentChannel::query()->where('code', 'va_bca')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'Status' => 200,
            'Data' => [
                'PaymentCode' => '3811800034254667',
                'PaymentChannel' => 'BCA',
                'PaymentName' => 'Hallo Bali',
            ],
        ]);

        $this->assertSame('va', $result['instructions']['type']);
        $this->assertSame('3811800034254667', $result['instructions']['va_number']);
        $this->assertSame('BCA', $result['instructions']['bank_name']);
    }

    public function test_normalizes_ipaymu_ewallet_response(): void
    {
        $channel = PaymentChannel::query()->where('code', 'gopay')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'Status' => 200,
            'Data' => [
                'Url' => 'gopay://pay',
                'QrString' => 'qr-data',
            ],
        ]);

        $this->assertSame('ewallet', $result['instructions']['type']);
        $this->assertSame('gopay://pay', $result['instructions']['deeplink_url']);
        $this->assertSame('qr-data', $result['instructions']['qr_string']);
    }

    public function test_normalizes_ipaymu_qris_base64_image(): void
    {
        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');
        $base64 = base64_encode(str_repeat('x', 48));

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'Status' => 200,
            'Data' => [
                'QrString' => '000201010212',
                'QrImage' => $base64,
            ],
        ]);

        $this->assertSame('qris', $result['instructions']['type']);
        $this->assertSame('data:image/png;base64,'.$base64, $result['instructions']['qr_url']);
    }

    public function test_normalizes_ipaymu_qris_url(): void
    {
        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
        $invoice = $this->createPayableInvoice('ipaymu');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'Status' => 200,
            'Data' => [
                'QrUrl' => 'https://sandbox.ipaymu.com/qr/test.png?foo=1&bar=2',
            ],
        ]);

        $this->assertSame('https://sandbox.ipaymu.com/qr/test.png?foo=1&bar=2', $result['instructions']['qr_url']);
    }

    public function test_normalizes_midtrans_qris_qr_string_only(): void
    {
        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $gateway = PaymentGateway::query()->where('name', 'midtrans')->firstOrFail();
        $invoice = $this->createPayableInvoice('midtrans');

        $result = $this->normalizer->normalize($channel, $gateway, $invoice, [
            'qr_string' => '000201010212',
            'actions' => [],
        ]);

        $this->assertSame('000201010212', $result['instructions']['qr_string']);
        $this->assertSame('', $result['instructions']['qr_url']);
    }
}
