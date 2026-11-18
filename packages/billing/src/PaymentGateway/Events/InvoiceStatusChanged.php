<?php

namespace App\Domain\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Invoice payment status transition (pending → paid / expired / …).
 *
 * Routed to private-payments.invoice.{id}. Independent of the notification bell.
 */
class InvoiceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $invoiceId,
        public readonly string $status,
        public readonly string $previousStatus,
        public readonly ?string $paidAt,
        public readonly ?string $expiredAt,
        public readonly ?string $invoiceableType,
        public readonly ?string $invoiceableId,
        public readonly ?string $invoiceableStatus = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('payments.invoice.'.$this->invoiceId)];
    }

    public function broadcastAs(): string
    {
        return 'invoice.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'status' => $this->status,
            'previous_status' => $this->previousStatus,
            'paid_at' => $this->paidAt,
            'expired_at' => $this->expiredAt,
            'invoiceable_type' => $this->invoiceableType,
            'invoiceable_id' => $this->invoiceableId,
            'invoiceable_status' => $this->invoiceableStatus,
        ];
    }
}
