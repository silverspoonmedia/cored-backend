<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Model
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Playground\Models\Order;
use App\Domain\PaymentGateway\Models\Subscription;

// Class
class MidtransSnapParameterBuilder
{
    /**
     * Build Snap transaction params. Omits item_details to avoid gross_amount drift vs invoice total.
     *
     * @param  array{enabled_payments?: list<string>, credit_card?: array<string, mixed>, customer_details?: array<string, mixed>}  $options
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice, array $options = []): array
    {
        $gross = (float) $invoice->customer_gross_total;

        $params = [
            'transaction_details' => [
                'order_id' => $invoice->invoice_number,
                'gross_amount' => $gross,
            ],
        ];

        if ($invoice->isForOrder() && $invoice->invoiceable instanceof Order) {
            $order = $invoice->invoiceable;
            $order->loadMissing('details');
            $params['customer_details'] = $this->customerFromOrder(
                $order,
                $options['customer_details'] ?? null
            );
        } elseif ($invoice->isForSubscription() && $invoice->invoiceable instanceof Subscription) {
            $sub = $invoice->invoiceable;
            $params['customer_details'] = array_filter([
                'first_name' => $sub->subscriber_name,
                'email' => $sub->subscriber_email,
                'phone' => $sub->subscriber_phone,
            ]);
        }

        if (! empty($options['enabled_payments']) && is_array($options['enabled_payments'])) {
            $params['enabled_payments'] = array_values($options['enabled_payments']);
        }

        if (! empty($options['credit_card']) && is_array($options['credit_card'])) {
            $params['credit_card'] = $options['credit_card'];
        }

        if (! empty($options['customer_details']) && is_array($options['customer_details']) && isset($params['customer_details'])) {
            $params['customer_details'] = array_merge($params['customer_details'], $options['customer_details']);
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array<string, mixed>
     */
    protected function customerFromOrder(Order $order, ?array $override): array
    {
        $base = array_filter([
            'first_name' => $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
        ]);

        if ($override === null || $override === []) {
            return $base;
        }

        return array_filter(array_merge($base, $override));
    }
}
