<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCustomerDetailsResolver;

class MidtransSnapParameterBuilder
{
    public function __construct(
        private InvoiceCustomerDetailsResolver $customerDetails,
    ) {}

    /**
     * Build Snap transaction params. Omits item_details to avoid gross_amount drift vs invoice total.
     *
     * @param  array{enabled_payments?: list<string>, credit_card?: array<string, mixed>, customer_details?: array<string, mixed>}  $options
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice, array $options = []): array
    {
        $currency = strtoupper((string) ($invoice->currency ?: 'IDR'));
        $gross = (float) $invoice->customer_gross_total;

        if ($currency === 'IDR') {
            $gross = (int) round($gross, 0);
        }

        $params = [
            'transaction_details' => [
                'order_id' => $invoice->invoice_number,
                'gross_amount' => $gross,
            ],
            'customer_details' => $this->customerDetails->forMidtrans(
                $invoice,
                $options['customer_details'] ?? null,
            ),
        ];

        if (! empty($options['enabled_payments']) && is_array($options['enabled_payments'])) {
            $params['enabled_payments'] = array_values($options['enabled_payments']);
        }

        if (! empty($options['credit_card']) && is_array($options['credit_card'])) {
            $params['credit_card'] = $options['credit_card'];
        }

        return $params;
    }
}
