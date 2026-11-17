<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuInvoiceSynchronizer;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use App\Domain\Service\Services\ServiceFulfillmentService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IpaymuInvoiceSynchronizerStatusTest extends TestCase
{
    private IpaymuInvoiceSynchronizer $synchronizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->synchronizer = new IpaymuInvoiceSynchronizer(
            app(InvoiceFundsLedgerService::class),
            app(ServiceFulfillmentService::class),
            app(PaymentRealtime::class),
        );
    }

    #[DataProvider('paymentStatusProvider')]
    public function test_map_invoice_status(int $paymentStatus, string $expected): void
    {
        $this->assertSame($expected, $this->synchronizer->mapInvoiceStatus($paymentStatus));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function paymentStatusProvider(): array
    {
        return [
            'success' => [1, 'paid'],
            'success_unsettle' => [6, 'paid'],
            'escrow' => [7, 'paid'],
            'pending' => [0, 'pending'],
            'expired' => [-2, 'expired'],
            'cancel' => [2, 'cancelled'],
            'failed' => [5, 'failed'],
        ];
    }
}
