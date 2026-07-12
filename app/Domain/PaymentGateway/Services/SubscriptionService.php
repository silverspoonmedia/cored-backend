<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    /**
     * @return Collection<int, Subscription>
     */
    public function list(): Collection
    {
        return $this->subscriptions->all();
    }

    public function get(string $id): Subscription
    {
        return $this->subscriptions->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subscription
    {
        $subscription = $this->subscriptions->create($data);

        $paymentGatewayId = isset($data['payment_gateway_id']) && $data['payment_gateway_id'] !== ''
            ? (string) $data['payment_gateway_id']
            : null;

        $this->createPendingInvoice($subscription, $paymentGatewayId);

        return $subscription->fresh(['invoice']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Subscription
    {
        return $this->subscriptions->update($id, $data);
    }

    public function delete(string $id): void
    {
        $subscription = $this->subscriptions->findOrFail($id);
        $invoice = $subscription->invoice;

        if ($invoice !== null && $invoice->status === 'pending') {
            $invoice->delete();
        }

        $this->subscriptions->delete($id);
    }

    protected function createPendingInvoice(Subscription $subscription, ?string $paymentGatewayId): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoiceable_id' => $subscription->id,
            'invoiceable_type' => 'subscription',
            'payment_gateway_id' => $paymentGatewayId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => $subscription->amount,
            'payment_channel_fee' => 0,
            'customer_gross_total' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => 'pending',
        ]);

        $this->invoices->save($invoice);

        return $invoice;
    }
}
