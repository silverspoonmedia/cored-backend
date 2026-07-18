<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Enum
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
// Service
use App\Domain\PaymentGateway\Models\Invoice;
// Model
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceFulfillmentService;
// Internal
use App\Support\Encryption\DecryptSourceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Class
class MidtransInvoiceSynchronizer
{
    public function __construct(
        private InvoiceFundsLedgerService $fundsLedger,
        private ServiceFulfillmentService $serviceFulfillment,
    ) {}

    /**
     * Apply Midtrans Get Status (or notification) payload to the invoice and related order.
     *
     * @param  object|array<string, mixed>  $apiResponse
     */
    public function sync(Invoice $invoice, object|array $apiResponse): Invoice
    {
        return DecryptSourceContext::run(
            'payment.midtrans.invoice_sync',
            fn (): Invoice => $this->syncWithinContext($invoice, $apiResponse),
            ['invoice_id' => $invoice->id],
        );
    }

    /**
     * @param  object|array<string, mixed>  $apiResponse
     */
    private function syncWithinContext(Invoice $invoice, object|array $apiResponse): Invoice
    {
        $row = is_array($apiResponse) ? $apiResponse : json_decode(json_encode($apiResponse), true);

        if (! is_array($row)) {
            return $invoice;
        }

        $invoice->loadMissing('invoiceable');

        $this->assertAmountMatchesInvoice($invoice, $row);

        $transactionStatus = $row['transaction_status'] ?? null;
        $paymentType = $row['payment_type'] ?? null;
        $fraudStatus = $row['fraud_status'] ?? null;
        $transactionId = $row['transaction_id'] ?? null;

        $invoiceStatus = $this->mapInvoiceStatus($transactionStatus, $paymentType, $fraudStatus);

        $paidAt = null;
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            if ($invoice->paid_at === null) {
                if (! empty($row['settlement_time'])) {
                    $paidAt = Carbon::parse($row['settlement_time']);
                } elseif (! empty($row['transaction_time'])) {
                    $paidAt = Carbon::parse($row['transaction_time']);
                } else {
                    $paidAt = now();
                }
            }
        }

        $expiredAt = null;
        if ($transactionStatus === 'expire') {
            $expiredAt = now();
        }

        $paymentMethod = is_string($paymentType) && $paymentType !== ''
            ? $paymentType
            : $invoice->payment_method;

        return DB::transaction(function () use ($invoice, $row, $invoiceStatus, $transactionId, $paymentMethod, $paidAt, $expiredAt) {
            $invoice->status = $invoiceStatus;
            $invoice->payment_method = $paymentMethod;

            if (is_string($transactionId) && $transactionId !== '') {
                $invoice->external_transaction_id = $transactionId;
            }

            $invoice->gateway_response_payload = $row;

            if ($paidAt !== null) {
                $invoice->paid_at = $paidAt;
            }

            if ($expiredAt !== null) {
                $invoice->expired_at = $expiredAt;
            }

            $this->fundsLedger->applyMidtrans($invoice, $row, $invoiceStatus);

            $invoice->save();

            if ($invoice->invoiceable instanceof Subscription) {
                $this->syncSubscriptionIfNeeded($invoice->invoiceable, $invoiceStatus);
            } elseif ($invoice->invoiceable instanceof ServicePurchase) {
                $this->syncServicePurchaseIfNeeded($invoice->invoiceable, $invoiceStatus);
            } elseif ($invoice->invoiceable instanceof ServiceAddonRenewal) {
                $this->syncServiceAddonRenewalIfNeeded($invoice->invoiceable, $invoiceStatus);
            }

            return $invoice->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function assertAmountMatchesInvoice(Invoice $invoice, array $row): void
    {
        if (! isset($row['gross_amount'])) {
            return;
        }

        $gross = (string) $row['gross_amount'];
        $expected = number_format((float) $invoice->customer_gross_total, 2, '.', '');

        if (round((float) $gross, 2) !== round((float) $expected, 2)) {
            throw new \RuntimeException('Midtrans gross_amount does not match invoice amount.');
        }
    }

    public function mapInvoiceStatus(mixed $transactionStatus, mixed $paymentType, mixed $fraudStatus): string
    {
        $transactionStatus = is_string($transactionStatus) ? $transactionStatus : '';

        if ($transactionStatus === 'settlement') {
            return 'paid';
        }

        if ($transactionStatus === 'capture') {
            if ($paymentType === 'credit_card' && $fraudStatus === 'challenge') {
                return 'challenge';
            }

            return 'paid';
        }

        return match ($transactionStatus) {
            'pending' => 'pending',
            'deny' => 'failed',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'failure' => 'failed',
            'refund' => 'refunded',
            'partial_refund' => 'partially_refunded',
            default => 'pending',
        };
    }

    protected function syncSubscriptionIfNeeded(Subscription $subscription, string $invoiceStatus): void
    {
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            if (! in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
                $subscription->status = SubscriptionStatus::Active;

                if ($subscription->started_at === null) {
                    $subscription->started_at = now();
                }

                $subscription->save();
            }

            return;
        }

        if (in_array($invoiceStatus, ['cancelled', 'expired', 'failed'], true)) {
            if ($subscription->status === SubscriptionStatus::Trial) {
                $subscription->status = SubscriptionStatus::Cancelled;
                $subscription->save();
            }
        }
    }

    protected function syncServicePurchaseIfNeeded(ServicePurchase $purchase, string $invoiceStatus): void
    {
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            $this->serviceFulfillment->fulfill($purchase);

            return;
        }

        if (in_array($invoiceStatus, ['cancelled', 'expired'], true)) {
            $this->serviceFulfillment->markCancelled($purchase);

            return;
        }

        if ($invoiceStatus === 'failed') {
            $this->serviceFulfillment->markFailed($purchase);
        }
    }

    protected function syncServiceAddonRenewalIfNeeded(ServiceAddonRenewal $renewal, string $invoiceStatus): void
    {
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            $this->serviceFulfillment->fulfillAddonRenewal($renewal);

            return;
        }

        if (in_array($invoiceStatus, ['cancelled', 'expired'], true)) {
            $this->serviceFulfillment->markAddonRenewalCancelled($renewal);

            return;
        }

        if ($invoiceStatus === 'failed') {
            $this->serviceFulfillment->markAddonRenewalFailed($renewal);
        }
    }
}
