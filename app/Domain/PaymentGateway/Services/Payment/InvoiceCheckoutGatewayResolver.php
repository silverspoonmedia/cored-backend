<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;

class InvoiceCheckoutGatewayResolver
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
    ) {}

    public function assignGatewayIfMissing(Invoice $invoice, string $providerName, ?string $preferredGatewayId = null): Invoice
    {
        if ($invoice->payment_gateway_id !== null) {
            $invoice->loadMissing('paymentGateway');

            if ($invoice->paymentGateway?->name !== $providerName) {
                abort(422, __('api/payments.checkout.gateway_provider_mismatch'));
            }

            return $invoice;
        }

        $gateway = $preferredGatewayId !== null
            ? PaymentGateway::query()->where('id', $preferredGatewayId)->where('is_active', true)->first()
            : PaymentGateway::query()->where('name', $providerName)->where('is_active', true)->first();

        if ($gateway === null) {
            $gateway = PaymentGateway::defaultForInvoice();
        }

        if ($gateway->name !== $providerName) {
            abort(422, __('api/payments.checkout.gateway_provider_mismatch'));
        }

        $invoice->payment_gateway_id = $gateway->id;
        $this->invoices->save($invoice);

        return $invoice->fresh(['paymentGateway']);
    }

    public function resolveProviderNameForInvoice(?array $invoiceData): string
    {
        $provider = (string) data_get($invoiceData, 'payment_gateway.name');

        if ($provider !== '') {
            return $provider;
        }

        return PaymentGateway::defaultForInvoice()->name;
    }
}
