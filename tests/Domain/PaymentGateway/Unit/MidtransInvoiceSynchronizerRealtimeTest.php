<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Events\InvoiceStatusChanged;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MidtransInvoiceSynchronizerRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
            'notifications.delivery' => 'polling',
        ]);
    }

    public function test_sync_dispatches_status_changed_on_paid(): void
    {
        Event::fake();

        $purchase = ServicePurchase::factory()->pending()->create([
            'settlement_amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        app(MidtransInvoiceSynchronizer::class)->sync($invoice, [
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'transaction_id' => 'mid-rt-1',
            'gross_amount' => '100000.00',
            'settlement_time' => now()->toDateTimeString(),
        ]);

        Event::assertDispatched(InvoiceStatusChanged::class, function (InvoiceStatusChanged $e) use ($invoice): bool {
            return $e->invoiceId === (string) $invoice->id
                && $e->status === 'paid'
                && $e->previousStatus === 'pending';
        });
    }

    public function test_sync_does_not_dispatch_when_status_unchanged(): void
    {
        Event::fake();

        $purchase = ServicePurchase::factory()->pending()->create([
            'settlement_amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'qris',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        app(MidtransInvoiceSynchronizer::class)->sync($invoice, [
            'transaction_status' => 'pending',
            'payment_type' => 'qris',
            'transaction_id' => 'mid-rt-2',
            'gross_amount' => '100000.00',
        ]);

        Event::assertNotDispatched(InvoiceStatusChanged::class);
    }
}
