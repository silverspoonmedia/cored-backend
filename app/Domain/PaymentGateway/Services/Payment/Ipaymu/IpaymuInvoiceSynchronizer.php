<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

// Enum
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
// Service
use App\Domain\PaymentGateway\Models\Invoice;
// Model
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceFulfillmentService;
// Internal
use App\Support\Encryption\DecryptSourceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use iPaymu\iPaymu;

// Class
/**
 * Maps iPaymu transaction check {@see iPaymu::checkTransaction} payloads to invoices.
 * Payment status codes align with ipaymu-go-api PaymentStatus constants.
 */
class IpaymuInvoiceSynchronizer
{
    public function __construct(
        private InvoiceFundsLedgerService $fundsLedger,
        private ServiceFulfillmentService $serviceFulfillment,
    ) {}

    private const STATUS_EXPIRED = -2;

    private const STATUS_PENDING = 0;

    private const STATUS_SUCCESS = 1;

    private const STATUS_CANCEL = 2;

    private const STATUS_REFUND = 3;

    private const STATUS_ERROR = 4;

    private const STATUS_FAILED = 5;

    private const STATUS_SUCCESS_UNSETTLE = 6;

    private const STATUS_ESCROW = 7;

    /**
     * @param  array<string, mixed>  $checkResponse  Full JSON-decoded API response (Status, Data, Message).
     */
    public function sync(Invoice $invoice, array $checkResponse): Invoice
    {
        return DecryptSourceContext::run(
            'payment.ipaymu.invoice_sync',
            fn (): Invoice => $this->syncWithinContext($invoice, $checkResponse),
            ['invoice_id' => $invoice->id],
        );
    }

    /**
     * @param  array<string, mixed>  $checkResponse  Full JSON-decoded API response (Status, Data, Message).
     */
    private function syncWithinContext(Invoice $invoice, array $checkResponse): Invoice
    {
        $data = $checkResponse['Data'] ?? null;

        if (! is_array($data)) {
            return $invoice;
        }

        $invoice->loadMissing('invoiceable');

        $this->reconcileIpaymuMonetaryFields($invoice, $data);

        $paymentStatus = isset($data['Status']) ? (int) $data['Status'] : self::STATUS_PENDING;
        $invoiceStatus = $this->mapInvoiceStatus($paymentStatus);

        $paidAt = null;
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            if ($invoice->paid_at === null) {
                $paidAt = $this->parseDateField($data['SuccessDate'] ?? $data['SettlementDate'] ?? null) ?? now();
            }
        }

        $expiredAt = null;
        if ($paymentStatus === self::STATUS_EXPIRED) {
            $expiredAt = $this->parseDateField($data['ExpiredDate'] ?? null) ?? now();
        }

        $transactionId = $data['TransactionId'] ?? null;
        $paymentMethod = $data['PaymentChannel'] ?? $data['Via'] ?? null;
        $paymentMethod = is_string($paymentMethod) && $paymentMethod !== ''
            ? $paymentMethod
            : $invoice->payment_method;

        return DB::transaction(function () use ($invoice, $checkResponse, $data, $invoiceStatus, $transactionId, $paymentMethod, $paidAt, $expiredAt) {
            $invoice->status = $invoiceStatus;
            $invoice->payment_method = $paymentMethod;

            if (is_numeric($transactionId)) {
                $invoice->external_transaction_id = (string) $transactionId;
            }

            $invoice->gateway_response_payload = $checkResponse;

            if ($paidAt !== null) {
                $invoice->paid_at = $paidAt;
            }

            if ($expiredAt !== null) {
                $invoice->expired_at = $expiredAt;
            }

            $this->fundsLedger->applyIpaymu($invoice, $data, $invoiceStatus);

            $invoice->save();

            if ($invoice->invoiceable instanceof Subscription) {
                $this->syncSubscriptionIfNeeded($invoice->invoiceable, $invoiceStatus);
            } elseif ($invoice->invoiceable instanceof ServicePurchase) {
                $this->syncServicePurchaseIfNeeded($invoice->invoiceable, $invoiceStatus);
            }

            return $invoice->fresh();
        });
    }

    /**
     * Align {@see Invoice::$payment_channel_fee} and {@see Invoice::$customer_gross_total} from iPaymu
     * `checkTransaction` / notify Data (Amount, SubTotal, Fee) and validate against {@see Invoice::$subtotal}.
     *
     * Merchant sale stays on invoice.subtotal (source of truth). Provider fee + customer gross are updated.
     * SubTotal may differ by up to 1 IDR (float / whole-rupiah noise); larger gaps reject the sync.
     *
     * @param  array<string, mixed>  $data  `checkTransaction` Data or notify body (mixed key casing).
     */
    protected function reconcileIpaymuMonetaryFields(Invoice $invoice, array $data): void
    {
        $amount = $this->firstNumericField($data, ['Amount', 'amount', 'Total', 'total']);
        if ($amount === null) {
            return;
        }

        $gross = round($amount, 2);
        $ourSub = round((float) $invoice->subtotal, 2);
        $ourFee = round((float) $invoice->payment_channel_fee, 2);
        $ourGross = round((float) $invoice->customer_gross_total, 2);

        // Notify uses sub_total / paid_off; checkTransaction often uses SubTotal.
        $subTotal = $this->firstNumericField($data, [
            'SubTotal', 'Subtotal', 'sub_total', 'PaidOff', 'paid_off',
        ]);
        if ($subTotal !== null) {
            $apiSub = round($subTotal, 2);
            $epsilon = $this->subtotalMatchEpsilon($invoice);

            /*
             * Primary: SubTotal === merchant sale (invoice.subtotal).
             * Legacy self-heal: older charges sent customer_gross_total as amount, so iPaymu
             * SubTotal equals our previous gross (sale + fee). Accept when:
             *   apiSub ≈ ourGross  AND  apiSub > ourSub  AND  fee+gross identity holds.
             * Then rewrite fee/gross from provider; leave subtotal (true sale) unchanged.
             */
            $matchesSale = $this->amountsClose($apiSub, $ourSub, $epsilon);
            $matchesStaleGross = ! $matchesSale
                && $ourGross > $ourSub
                && $this->amountsClose($apiSub, $ourGross, $epsilon);

            if (! $matchesSale && ! $matchesStaleGross) {
                Log::warning('iPaymu SubTotal mismatch', [
                    'invoice_id' => $invoice->id,
                    'invoice_subtotal' => $ourSub,
                    'invoice_fee' => $ourFee,
                    'invoice_gross' => $ourGross,
                    'ipaymu_subtotal' => $apiSub,
                    'ipaymu_amount' => $gross,
                    'epsilon' => $epsilon,
                ]);

                throw new \RuntimeException('iPaymu SubTotal does not match invoice subtotal.');
            }

            if ($matchesStaleGross) {
                Log::notice('iPaymu SubTotal matched stale customer_gross_total; reconciling fee from provider', [
                    'invoice_id' => $invoice->id,
                    'invoice_subtotal' => $ourSub,
                    'ipaymu_subtotal' => $apiSub,
                    'ipaymu_amount' => $gross,
                ]);
            } elseif ($apiSub !== $ourSub) {
                Log::notice('iPaymu SubTotal within epsilon of invoice subtotal', [
                    'invoice_id' => $invoice->id,
                    'invoice_subtotal' => $ourSub,
                    'ipaymu_subtotal' => $apiSub,
                    'epsilon' => $epsilon,
                ]);
            }

            $fee = $this->firstNumericField($data, ['Fee', 'fee']);
            if ($fee !== null) {
                $invoice->payment_channel_fee = round(max(0.0, $fee), 2);
            } else {
                $invoice->payment_channel_fee = round(max(0.0, $gross - $apiSub), 2);
            }

            $invoice->customer_gross_total = $gross;

            if (! $this->amountsClose($apiSub + (float) $invoice->payment_channel_fee, $gross)) {
                throw new \RuntimeException('iPaymu Amount does not match SubTotal plus channel fee.');
            }

            return;
        }

        if ($this->amountsClose($gross, $ourGross)) {
            return;
        }

        if ($this->amountsClose($gross, $ourSub) && $ourFee === 0.0 && $this->amountsClose($ourGross, $ourSub)) {
            $invoice->customer_gross_total = $gross;

            return;
        }

        if ($ourFee === 0.0 && $this->amountsClose($ourGross, $ourSub) && $gross > $ourSub) {
            $invoice->payment_channel_fee = round($gross - $ourSub, 2);
            $invoice->customer_gross_total = $gross;

            return;
        }

        throw new \RuntimeException('iPaymu transaction amount does not match invoice totals.');
    }

    /**
     * IDR PG rails are whole-rupiah; allow 1 unit drift. Other currencies keep 0.02.
     */
    protected function subtotalMatchEpsilon(Invoice $invoice): float
    {
        $currency = strtoupper((string) ($invoice->currency ?? 'IDR'));

        return $currency === 'IDR' ? 1.0 : 0.02;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    protected function firstNumericField(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $v = $data[$key];
            if (is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }

    protected function amountsClose(float $a, float $b, float $epsilon = 0.02): bool
    {
        return abs(round($a, 2) - round($b, 2)) <= $epsilon;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @internal Exposed for unit tests via reflection-friendly access.
     */
    public function reconcileMonetaryFieldsForTest(Invoice $invoice, array $data): void
    {
        $this->reconcileIpaymuMonetaryFields($invoice, $data);
    }

    public function mapInvoiceStatus(int $paymentStatus): string
    {
        return match ($paymentStatus) {
            self::STATUS_SUCCESS => 'paid',
            self::STATUS_SUCCESS_UNSETTLE => 'paid',
            self::STATUS_ESCROW => 'paid',
            self::STATUS_PENDING => 'pending',
            self::STATUS_EXPIRED => 'expired',
            self::STATUS_CANCEL => 'cancelled',
            self::STATUS_REFUND => 'refunded',
            self::STATUS_ERROR, self::STATUS_FAILED => 'failed',
            default => 'pending',
        };
    }

    protected function parseDateField(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
}
