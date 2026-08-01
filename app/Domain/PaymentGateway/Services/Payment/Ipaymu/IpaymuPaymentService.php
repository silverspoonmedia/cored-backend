<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutGatewayResolver;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCustomerDetailsResolver;
use App\Domain\PaymentGateway\Services\Payment\InvoicePaymentInitiationGuard;
use App\Support\Encryption\DecryptSourceContext;
use iPaymu\iPaymu;
use Illuminate\Support\Facades\Route;

class IpaymuPaymentService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private IpaymuInvoiceSynchronizer $synchronizer,
        private InvoicePaymentInitiationGuard $initiationGuard,
        private InvoiceCheckoutGatewayResolver $gatewayResolver,
        private InvoiceActiveCheckoutCancellationService $checkoutCancellation,
        private InvoiceCustomerDetailsResolver $customerDetails,
    ) {}

    /**
     * @param  array{invoice_id: string, buyer?: array{name?: string, email?: string, phone?: string}, return_url?: string, cancel_url?: string}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    public function createSnapToken(array $input): array
    {
        return DecryptSourceContext::run('payment.ipaymu.checkout_snap', fn (): array => $this->createSnapTokenWithinContext($input));
    }

    /**
     * @param  array{invoice_id: string, buyer?: array{name?: string, email?: string, phone?: string}, return_url?: string, cancel_url?: string}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    private function createSnapTokenWithinContext(array $input): array
    {
        $invoiceModel = $this->invoices->findOrFail($input['invoice_id']);
        $this->checkoutCancellation->abandonActiveCheckout($invoiceModel);

        $invoice = $this->gatewayResolver->assignGatewayIfMissing(
            $invoiceModel->refresh(),
            'ipaymu',
            $input['payment_gateway_id'] ?? null,
        );

        $guardResult = $this->initiationGuard->ensurePayableForProvider($invoice->id, 'ipaymu', rejectActiveCheckout: true);
        if (is_array($guardResult)) {
            return $guardResult;
        }
        $invoice = $guardResult;

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $client = IpaymuSdk::makeClient($gateway);

        $buyer = $this->resolveBuyer($invoice, $input['buyer'] ?? null);

        $returnUrl = $input['return_url'] ?? self::defaultReturnUrl();
        $cancelUrl = $input['cancel_url'] ?? self::defaultCancelUrl();

        $client->setURL([
            'ureturn' => $returnUrl,
            'ucancel' => $cancelUrl,
            'unotify' => IpaymuNotifyUrl::webhookUrl(),
        ]);

        $client->setBuyer($buyer);
        $this->applyCartFromInvoice($client, $invoice);

        $invoice->gateway_request_payload = [
            'mode' => 'redirect',
            'referenceId' => $invoice->invoice_number,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ];

        try {
            $response = $client->redirectPayment(['referenceId' => $invoice->invoice_number]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        $invoice->gateway_response_payload = is_array($response) ? $response : [];

        if (isset($response['Status']) && (int) $response['Status'] === 200) {
            $tid = data_get($response, 'Data.TransactionId');
            if (is_numeric($tid) && ! $this->hasExternalTransactionId($invoice)) {
                $invoice->external_transaction_id = (string) $tid;
            }
        }

        $this->invoices->save($invoice);

        if (! isset($response['Status']) || (int) $response['Status'] !== 200) {
            $msg = is_array($response) ? ($response['Message'] ?? 'iPaymu redirect payment failed.') : 'iPaymu redirect payment failed.';

            return ['ok' => false, 'message' => (string) $msg, 'http' => 502];
        }

        $sessionId = (string) (data_get($response, 'Data.SessionId')
            ?? data_get($response, 'Data.SessionID')
            ?? '');
        $redirectUrl = (string) (data_get($response, 'Data.Url') ?? '');

        return [
            'ok' => true,
            'data' => [
                'snap_token' => $sessionId !== '' ? $sessionId : null,
                'redirect_url' => $redirectUrl !== '' ? $redirectUrl : null,
                'client_key' => null,
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->invoice_number,
            ],
            'message_key' => 'api/payments.ipaymu.snap_created',
            'http' => 201,
        ];
    }

    /**
     * @param  array{invoice_id: string, payment_method: string, payment_channel: string, buyer?: array{name?: string, email?: string, phone?: string}, expired?: string, expired_type?: string}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    public function charge(array $input): array
    {
        return DecryptSourceContext::run('payment.ipaymu.checkout_charge', fn (): array => $this->chargeWithinContext($input));
    }

    /**
     * @param  array{invoice_id: string, payment_method: string, payment_channel: string, buyer?: array{name?: string, email?: string, phone?: string}, expired?: string, expired_type?: string}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    private function chargeWithinContext(array $input): array
    {
        $guardResult = $this->initiationGuard->ensurePayableForProvider($input['invoice_id'], 'ipaymu', rejectActiveCheckout: true);
        if (is_array($guardResult)) {
            return $guardResult;
        }
        $invoice = $guardResult;

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $client = IpaymuSdk::makeClient($gateway);

        $buyer = $this->resolveBuyer($invoice, $input['buyer'] ?? null);

        $client->setURL([
            'ureturn' => '',
            'ucancel' => '',
            'unotify' => IpaymuNotifyUrl::webhookUrl(),
        ]);

        $client->setBuyer($buyer);
        // Merchant sale only — iPaymu adds channel fee on top (FeeDirection BUYER).
        // Never send customer_gross_total: that already includes a prior fee and becomes SubTotal again.
        $this->resetChannelFeeForFreshCharge($invoice);
        $this->applyCartFromInvoice($client, $invoice);

        $merchantAmount = (float) $invoice->subtotal;
        $direct = [
            'amount' => $merchantAmount,
            'paymentMethod' => $input['payment_method'],
            'paymentChannel' => $input['payment_channel'],
            'referenceId' => $invoice->invoice_number,
            'expired' => $input['expired'] ?? '1',
            'expiredType' => $input['expired_type'] ?? 'days',
        ];

        $invoice->gateway_request_payload = array_merge(['mode' => 'direct'], $direct);

        try {
            $response = $client->directPayment($direct);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        $invoice->gateway_response_payload = is_array($response) ? $response : [];

        if (isset($response['Status']) && (int) $response['Status'] === 200) {
            $tid = data_get($response, 'Data.TransactionId');
            if (is_numeric($tid) && ! $this->hasExternalTransactionId($invoice)) {
                $invoice->external_transaction_id = (string) $tid;
            }

            $data = $response['Data'] ?? null;
            if (is_array($data)) {
                $this->applyChargeResponseMoney($invoice, $data);
            }
        }

        $this->invoices->save($invoice);

        if (! isset($response['Status']) || (int) $response['Status'] !== 200) {
            $msg = is_array($response) ? ($response['Message'] ?? 'iPaymu direct payment failed.') : 'iPaymu direct payment failed.';

            return ['ok' => false, 'message' => (string) $msg, 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => $response,
            'message_key' => 'api/payments.ipaymu.charge_created',
            'http' => 201,
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function getTransactionStatus(string $orderId): array
    {
        $invoice = $this->invoices->findByInvoiceNumberForGateway($orderId, 'ipaymu');

        if ($invoice === null) {
            return ['ok' => false, 'message' => __('api/payments.ipaymu.invoice_not_found'), 'http' => 404];
        }

        $tid = $invoice->external_transaction_id;

        if ($tid === null || $tid === '') {
            return ['ok' => false, 'message' => __('api/payments.ipaymu.missing_transaction_id'), 'http' => 422];
        }

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $client = IpaymuSdk::makeClient($gateway);

        try {
            $response = $client->checkTransaction((int) $tid);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => is_array($response) ? $response : [],
            'message_key' => 'api/payments.ipaymu.status_retrieved',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function syncInvoiceStatus(Invoice $invoice): array
    {
        return DecryptSourceContext::run('payment.ipaymu.sync_status', fn (): array => $this->syncInvoiceStatusWithinContext($invoice));
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    private function syncInvoiceStatusWithinContext(Invoice $invoice): array
    {
        $this->assertIpaymuInvoice($invoice);

        $tid = $invoice->external_transaction_id;

        if ($tid === null || $tid === '') {
            return ['ok' => false, 'message' => __('api/payments.ipaymu.missing_transaction_id'), 'http' => 422];
        }

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $client = IpaymuSdk::makeClient($gateway);

        try {
            $response = $client->checkTransaction((int) $tid);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        if (! is_array($response) || (int) ($response['Status'] ?? 0) !== 200) {
            return [
                'ok' => false,
                'message' => is_array($response) ? (string) ($response['Message'] ?? 'Check transaction failed.') : 'Check transaction failed.',
                'http' => 502,
            ];
        }

        try {
            $updated = $this->synchronizer->sync($invoice, $response);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 422];
        }

        return [
            'ok' => true,
            'data' => [
                'invoice' => [
                    'id' => $updated->id,
                    'invoice_number' => $updated->invoice_number,
                    'status' => $updated->status,
                    'external_transaction_id' => $updated->external_transaction_id,
                    'paid_at' => $updated->paid_at?->toIso8601String(),
                    'expired_at' => $updated->expired_at?->toIso8601String(),
                    'invoiceable_status' => $this->resolveInvoiceableStatus($updated),
                ],
                'ipaymu' => $response,
            ],
            'message_key' => 'api/payments.ipaymu.sync_ok',
        ];
    }

    /**
     * @param  ?array{name?: string, email?: string, phone?: string}  $buyerFromRequest
     * @return array{name: string, email: string, phone: string}
     */
    protected function resolveBuyer(Invoice $invoice, ?array $buyerFromRequest): array
    {
        return $this->customerDetails->forIpaymu($invoice, $buyerFromRequest);
    }

    protected function applyCartFromInvoice(iPaymu $client, Invoice $invoice): void
    {
        $amount = (string) (float) $invoice->subtotal;
        $label = 'Invoice '.$invoice->invoice_number;

        $client->addCart([
            'product' => [$label],
            'quantity' => ['1'],
            'price' => [$amount],
            'description' => ['Payment '.$invoice->invoice_number],
            'weight' => ['1'],
            'length' => ['10'],
            'width' => ['10'],
            'height' => ['10'],
        ]);
    }

    /**
     * Before a new charge, drop any fee/gross leftover from a previous attempt so cart = sale only.
     */
    protected function resetChannelFeeForFreshCharge(Invoice $invoice): void
    {
        $sub = round((float) $invoice->subtotal, 2);
        $fee = round((float) $invoice->payment_channel_fee, 2);
        $gross = round((float) $invoice->customer_gross_total, 2);

        if ($fee === 0.0 && $gross === $sub) {
            return;
        }

        $invoice->payment_channel_fee = 0;
        $invoice->customer_gross_total = $sub;
    }

    /**
     * Capture iPaymu fee + total from successful directPayment Data (SubTotal must match cart).
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyChargeResponseMoney(Invoice $invoice, array $data): void
    {
        $apiSub = null;
        foreach (['SubTotal', 'Subtotal', 'sub_total'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $apiSub = round((float) $data[$key], 2);
                break;
            }
        }

        $ourSub = round((float) $invoice->subtotal, 2);
        if ($apiSub !== null && abs($apiSub - $ourSub) > 1.0) {
            return;
        }

        $fee = null;
        foreach (['Fee', 'fee'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $fee = round(max(0.0, (float) $data[$key]), 2);
                break;
            }
        }

        $total = null;
        foreach (['Total', 'total', 'Amount', 'amount'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $total = round((float) $data[$key], 2);
                break;
            }
        }

        if ($fee !== null) {
            $invoice->payment_channel_fee = $fee;
        }

        if ($total !== null) {
            $invoice->customer_gross_total = $total;
        } elseif ($fee !== null) {
            $invoice->customer_gross_total = round($ourSub + $fee, 2);
        }
    }

    protected function assertIpaymuInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('paymentGateway');

        if ($invoice->paymentGateway === null || $invoice->paymentGateway->name !== 'ipaymu') {
            abort(422, __('api/payments.ipaymu.invoice_not_ipaymu'));
        }
    }

    protected function assertInvoicePayable(Invoice $invoice): void
    {
        if ($invoice->status !== 'pending' && $invoice->status !== 'challenge') {
            abort(422, __('api/payments.ipaymu.invoice_not_payable'));
        }
    }

    private function hasExternalTransactionId(Invoice $invoice): bool
    {
        $tid = $invoice->external_transaction_id;

        return $tid !== null && $tid !== '';
    }

    public static function defaultReturnUrl(): string
    {
        if (Route::has('payment.ipaymu.return')) {
            return route('payment.ipaymu.return', [], true);
        }

        return rtrim((string) config('app.url'), '/').'/payment/ipaymu/return';
    }

    public static function defaultCancelUrl(): string
    {
        if (Route::has('payment.ipaymu.cancel')) {
            return route('payment.ipaymu.cancel', [], true);
        }

        return rtrim((string) config('app.url'), '/').'/payment/ipaymu/cancel';
    }

    /**
     * Resolve the invoiceable's current status string for sync-status responses.
     *
     * Mirrors PaymentRealtime::resolveInvoiceableStatus so checkout JS can
     * soft-update the purchase status badge on polling without a reload.
     */
    private function resolveInvoiceableStatus(Invoice $invoice): ?string
    {
        $invoiceable = $invoice->invoiceable;

        if ($invoiceable === null) {
            return null;
        }

        $status = $invoiceable->status ?? null;

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return $status !== null ? (string) $status : null;
    }
}
