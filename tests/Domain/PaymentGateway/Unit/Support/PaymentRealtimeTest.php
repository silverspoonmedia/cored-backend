<?php

namespace Tests\Domain\PaymentGateway\Unit\Support;

use App\Domain\PaymentGateway\Events\InvoiceStatusChanged;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PaymentRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function enableBroadcast(): void
    {
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

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_enabled_follows_broadcast_credentials_not_notification_type(): void
    {
        $rt = app(PaymentRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());
        $this->assertNull($rt->clientConfig());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
        $this->assertIsArray($rt->clientConfig());
        $this->assertSame('key', $rt->clientConfig()['key']);
    }

    public function test_invoice_status_changed_noop_when_unconfigured(): void
    {
        Event::fake([InvoiceStatusChanged::class]);
        $this->disableBroadcast();

        $invoice = $this->pendingInvoice();
        app(PaymentRealtime::class)->invoiceStatusChanged($invoice, 'pending');

        Event::assertNotDispatched(InvoiceStatusChanged::class);
    }

    public function test_invoice_status_changed_noop_when_status_unchanged(): void
    {
        Event::fake([InvoiceStatusChanged::class]);
        $this->enableBroadcast();

        $invoice = $this->pendingInvoice();
        $invoice->status = 'pending';
        app(PaymentRealtime::class)->invoiceStatusChanged($invoice, 'pending');

        Event::assertNotDispatched(InvoiceStatusChanged::class);
    }

    public function test_invoice_status_changed_dispatches_when_configured(): void
    {
        Event::fake([InvoiceStatusChanged::class]);
        $this->enableBroadcast();

        $purchase = ServicePurchase::factory()->pending()->create();
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
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(PaymentRealtime::class)->invoiceStatusChanged($invoice, 'pending');

        Event::assertDispatched(InvoiceStatusChanged::class, function (InvoiceStatusChanged $e) use ($invoice, $purchase): bool {
            return $e->invoiceId === (string) $invoice->id
                && $e->status === 'paid'
                && $e->previousStatus === 'pending'
                && $e->invoiceableType === 'service_purchase'
                && $e->invoiceableId === (string) $purchase->id
                && $e->broadcastAs() === 'invoice.status.changed'
                && $e->broadcastOn()[0]->name === 'private-payments.invoice.'.$invoice->id;
        });
    }

    private function pendingInvoice(): Invoice
    {
        $purchase = ServicePurchase::factory()->pending()->create();

        return Invoice::query()->create([
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
    }
}
