<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;

class InvoiceAttachableService implements InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForServicePurchase(
        ServicePurchase $purchase,
        ?string $paymentGatewayId,
    ): Invoice {
        return $this->createPending(
            invoiceableId: $purchase->id,
            invoiceableType: 'service_purchase',
            settlementCurrency: (string) $purchase->settlement_currency,
            settlementAmount: (float) $purchase->settlement_amount,
            paymentGatewayId: $paymentGatewayId,
        );
    }

    public function createPendingInvoiceForServiceAddonRenewal(
        ServiceAddonRenewal $renewal,
        ?string $paymentGatewayId,
    ): Invoice {
        return $this->createPending(
            invoiceableId: $renewal->id,
            invoiceableType: 'service_addon_renewal',
            settlementCurrency: (string) $renewal->settlement_currency,
            settlementAmount: (float) $renewal->settlement_amount,
            paymentGatewayId: $paymentGatewayId,
        );
    }

    private function createPending(
        string $invoiceableId,
        string $invoiceableType,
        string $settlementCurrency,
        float $settlementAmount,
        ?string $paymentGatewayId,
    ): Invoice {
        $currency = strtoupper($settlementCurrency) ?: 'IDR';
        $amount = $currency === 'IDR' ? (float) round($settlementAmount, 0) : round($settlementAmount, 2);

        return Invoice::query()->create([
            'invoiceable_id' => $invoiceableId,
            'invoiceable_type' => $invoiceableType,
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
