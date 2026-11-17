<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Support;

use App\Domain\PaymentGateway\Events\InvoiceListChanged;
use App\Domain\PaymentGateway\Events\InvoiceStatusChanged;
use App\Domain\PaymentGateway\Events\PaymentChannelChanged;
use App\Domain\PaymentGateway\Events\PaymentGatewayChanged;
use App\Domain\PaymentGateway\Events\PaymentRoutingCatalogChanged;
use App\Domain\PaymentGateway\Events\SubscriptionChanged;
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

    public function test_routing_catalog_changed_noop_when_unconfigured(): void
    {
        Event::fake([PaymentRoutingCatalogChanged::class]);
        $this->disableBroadcast();

        app(PaymentRealtime::class)->routingCatalogChanged('channel_updated', 'qris');

        Event::assertNotDispatched(PaymentRoutingCatalogChanged::class);
    }

    public function test_routing_catalog_changed_dispatches_when_configured(): void
    {
        Event::fake([PaymentRoutingCatalogChanged::class]);
        $this->enableBroadcast();

        app(PaymentRealtime::class)->routingCatalogChanged('route_updated', 'va_bca');

        Event::assertDispatched(PaymentRoutingCatalogChanged::class, function (PaymentRoutingCatalogChanged $e): bool {
            return $e->reason === 'route_updated'
                && $e->channelCode === 'va_bca'
                && $e->broadcastAs() === 'routing.catalog.changed'
                && $e->broadcastOn()[0]->name === 'private-payments.routing';
        });
    }

    public function test_gateway_changed_noop_when_unconfigured(): void
    {
        Event::fake([PaymentGatewayChanged::class]);
        $this->disableBroadcast();

        app(PaymentRealtime::class)->gatewayChanged('created', 'gw-1');

        Event::assertNotDispatched(PaymentGatewayChanged::class);
    }

    public function test_gateway_changed_dispatches_when_configured(): void
    {
        Event::fake([PaymentGatewayChanged::class]);
        $this->enableBroadcast();

        app(PaymentRealtime::class)->gatewayChanged('updated', 'gw-2');

        Event::assertDispatched(PaymentGatewayChanged::class, function (PaymentGatewayChanged $e): bool {
            return $e->action === 'updated'
                && $e->gatewayId === 'gw-2'
                && $e->broadcastAs() === 'payments.gateway.changed'
                && $e->broadcastOn()[0]->name === 'private-payments.admin';
        });
    }

    public function test_channel_changed_noop_when_unconfigured(): void
    {
        Event::fake([PaymentChannelChanged::class]);
        $this->disableBroadcast();

        app(PaymentRealtime::class)->channelChanged('disabled', 'qris');

        Event::assertNotDispatched(PaymentChannelChanged::class);
    }

    public function test_channel_changed_dispatches_when_configured(): void
    {
        Event::fake([PaymentChannelChanged::class]);
        $this->enableBroadcast();

        app(PaymentRealtime::class)->channelChanged('enabled', 'va_bca');

        Event::assertDispatched(PaymentChannelChanged::class, function (PaymentChannelChanged $e): bool {
            return $e->action === 'enabled'
                && $e->channelCode === 'va_bca'
                && $e->broadcastAs() === 'payments.channel.changed'
                && $e->broadcastOn()[0]->name === 'private-payments.admin';
        });
    }

    public function test_subscription_changed_noop_when_unconfigured(): void
    {
        Event::fake([SubscriptionChanged::class]);
        $this->disableBroadcast();

        app(PaymentRealtime::class)->subscriptionChanged('created', 'sub-1');

        Event::assertNotDispatched(SubscriptionChanged::class);
    }

    public function test_subscription_changed_dispatches_when_configured(): void
    {
        Event::fake([SubscriptionChanged::class]);
        $this->enableBroadcast();

        app(PaymentRealtime::class)->subscriptionChanged('deleted', 'sub-7');

        Event::assertDispatched(SubscriptionChanged::class, function (SubscriptionChanged $e): bool {
            return $e->action === 'deleted'
                && $e->subscriptionId === 'sub-7'
                && $e->broadcastAs() === 'payments.subscription.changed'
                && $e->broadcastOn()[0]->name === 'private-payments.admin';
        });
    }

    public function test_invoice_status_changed_also_pings_admin_list(): void
    {
        Event::fake([InvoiceStatusChanged::class, InvoiceListChanged::class]);
        $this->enableBroadcast();

        $invoice = $this->pendingInvoice();
        $invoice->status = 'paid';
        $invoice->paid_at = now();
        app(PaymentRealtime::class)->invoiceStatusChanged($invoice, 'pending');

        Event::assertDispatched(InvoiceStatusChanged::class);
        Event::assertDispatched(InvoiceListChanged::class, function (InvoiceListChanged $e): bool {
            return $e->action === 'paid';
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
