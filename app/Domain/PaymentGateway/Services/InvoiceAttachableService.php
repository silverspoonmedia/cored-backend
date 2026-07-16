<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Models\ServicePurchase;

class InvoiceAttachableService implements InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForServicePurchase(
        ServicePurchase $purchase,
        ?string $paymentGatewayId,
    ): Invoice {
        $currency = strtoupper((string) $purchase->settlement_currency) ?: 'IDR';
        $amount = (float) $purchase->settlement_amount;
        // IDR PG rails are whole rupiah (iPaymu/Midtrans).
        $amount = $currency === 'IDR' ? (float) round($amount, 0) : round($amount, 2);

        return Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $paymentGatewayId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => $amount,
            'payment_channel_fee' => 0,
            'customer_gross_total' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);
    }
}
