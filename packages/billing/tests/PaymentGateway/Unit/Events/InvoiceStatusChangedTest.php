<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Events;

use App\Domain\PaymentGateway\Events\InvoiceStatusChanged;
use App\Domain\PaymentGateway\Tests\TestCase;
use Illuminate\Broadcasting\PrivateChannel;

class InvoiceStatusChangedTest extends TestCase
{
    public function test_broadcast_contract(): void
    {
        $event = new InvoiceStatusChanged(
            invoiceId: 'inv-1',
            status: 'paid',
            previousStatus: 'pending',
            paidAt: '2026-01-01T00:00:00+00:00',
            expiredAt: null,
            invoiceableType: 'service_purchase',
            invoiceableId: 'purchase-1',
            invoiceableStatus: 'paid',
        );

        $this->assertSame('invoice.status.changed', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-payments.invoice.inv-1', $channels[0]->name);

        $this->assertSame([
            'invoice_id' => 'inv-1',
            'status' => 'paid',
            'previous_status' => 'pending',
            'paid_at' => '2026-01-01T00:00:00+00:00',
            'expired_at' => null,
            'invoiceable_type' => 'service_purchase',
            'invoiceable_id' => 'purchase-1',
            'invoiceable_status' => 'paid',
        ], $event->broadcastWith());
    }
}
