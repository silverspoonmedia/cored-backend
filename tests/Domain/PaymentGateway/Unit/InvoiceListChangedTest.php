<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Events\InvoiceListChanged;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InvoiceListChangedTest extends TestCase
{
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
        ]);
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_noop_when_unconfigured(): void
    {
        Event::fake([InvoiceListChanged::class]);
        $this->disableBroadcast();

        app(PaymentRealtime::class)->invoiceListChanged('paid');

        Event::assertNotDispatched(InvoiceListChanged::class);
    }

    public function test_dispatches_when_configured(): void
    {
        Event::fake([InvoiceListChanged::class]);
        $this->enableBroadcast();

        app(PaymentRealtime::class)->invoiceListChanged('paid');

        Event::assertDispatched(InvoiceListChanged::class, function (InvoiceListChanged $e): bool {
            return $e->action === 'paid';
        });
    }
}
