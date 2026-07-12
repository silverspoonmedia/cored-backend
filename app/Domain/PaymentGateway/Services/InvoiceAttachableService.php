<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Playground\Models\Order;

class InvoiceAttachableService implements InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForOrder(Order $order, ?string $paymentGatewayId): Invoice
    {
        return Invoice::query()->create([
            'invoiceable_id' => $order->id,
            'invoiceable_type' => 'order',
            'payment_gateway_id' => $paymentGatewayId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => $order->grand_total,
            'payment_channel_fee' => 0,
            'customer_gross_total' => $order->grand_total,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }

    public function syncOrderInvoiceAmountIfPending(Order $order): void
    {
        $invoice = $order->invoice;

        if ($invoice === null || $invoice->status !== 'pending') {
            return;
        }

        $invoice->subtotal = $order->grand_total;
        $invoice->payment_channel_fee = 0;
        $invoice->customer_gross_total = $order->grand_total;
        $invoice->save();
    }

    public function deletePendingInvoiceForOrder(Order $order): void
    {
        $invoice = $order->invoice;

        if ($invoice === null || $invoice->status !== 'pending') {
            return;
        }

        if ($invoice->external_transaction_id !== null) {
            return;
        }

        $invoice->delete();
    }
}
