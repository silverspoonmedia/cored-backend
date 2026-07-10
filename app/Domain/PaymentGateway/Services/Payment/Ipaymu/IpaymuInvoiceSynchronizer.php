<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

// Enum
use App\Domain\Playground\Enums\OrderStatus;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
// Service
use App\Domain\PaymentGateway\Models\Invoice;
// Model
use App\Domain\Playground\Models\Order;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService;
// Internal
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use iPaymu\iPaymu;

// Class
/**
 * Maps iPaymu transaction check {@see iPaymu::checkTransaction} payloads to invoices.
 * Payment status codes align with ipaymu-go-api PaymentStatus constants.
 */
class IpaymuInvoiceSynchronizer
{
    public function __construct(
        private InvoiceFundsLedgerService $fundsLedger
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

            if ($invoice->invoiceable instanceof Order) {
                $this->syncOrderIfNeeded($invoice->invoiceable, $invoiceStatus);
            } elseif ($invoice->invoiceable instanceof Subscription) {
                $this->syncSubscriptionIfNeeded($invoice->invoiceable, $invoiceStatus);
            }

            return $invoice->fresh();
        });
    }

    /**
     * Align {@see Invoice::$payment_channel_fee} and {@see Invoice::$customer_gross_total} from iPaymu
     * `checkTransaction` Data (Amount, SubTotal, Fee) and validate against {@see Invoice::$subtotal}.
     *
     * @param  array<string, mixed>  $data  `checkTransaction` Data object (mixed key casing from JSON).
     */
    protected function reconcileIpaymuMonetaryFields(Invoice $invoice, array $data): void
    {
        $amount = $this->firstNumericField($data, ['Amount', 'amount']);
        if ($amount === null) {
            return;
        }

        $gross = round($amount, 2);
        $ourSub = round((float) $invoice->subtotal, 2);
        $ourFee = round((float) $invoice->payment_channel_fee, 2);
        $ourGross = round((float) $invoice->customer_gross_total, 2);

        $subTotal = $this->firstNumericField($data, ['SubTotal', 'Subtotal', 'sub_total']);
        if ($subTotal !== null) {
            $apiSub = round($subTotal, 2);
            if ($apiSub !== $ourSub) {
                throw new \RuntimeException('iPaymu SubTotal does not match invoice subtotal.');
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

        if ($gross === $ourGross) {
            return;
        }

        if ($gross === $ourSub && $ourFee === 0.0 && $ourGross === $ourSub) {
            $invoice->customer_gross_total = $gross;

            return;
        }

        if ($ourFee === 0.0 && $ourGross === $ourSub && $gross > $ourSub) {
            $invoice->payment_channel_fee = round($gross - $ourSub, 2);
            $invoice->customer_gross_total = $gross;

            return;
        }

        throw new \RuntimeException('iPaymu transaction amount does not match invoice totals.');
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

    protected function syncOrderIfNeeded(Order $order, string $invoiceStatus): void
    {
        if (in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            if ($order->status !== OrderStatus::Completed) {
                $order->status = OrderStatus::Completed;
                $order->save();
            }

            return;
        }

        if (in_array($invoiceStatus, ['cancelled', 'expired', 'failed'], true)) {
            if ($order->status === OrderStatus::Pending) {
                $order->status = OrderStatus::Cancelled;
                $order->save();
            }
        }
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
}
