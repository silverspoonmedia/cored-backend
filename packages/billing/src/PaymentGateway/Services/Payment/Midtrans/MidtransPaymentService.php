<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutGatewayResolver;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCustomerDetailsResolver;
use App\Domain\PaymentGateway\Services\Payment\InvoicePaymentInitiationGuard;
use App\Support\Encryption\DecryptSourceContext;
use Midtrans\CoreApi;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransPaymentService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private MidtransInvoiceSynchronizer $synchronizer,
        private MidtransSnapParameterBuilder $snapParameterBuilder,
        private InvoicePaymentInitiationGuard $initiationGuard,
        private InvoiceCheckoutGatewayResolver $gatewayResolver,
        private InvoiceActiveCheckoutCancellationService $checkoutCancellation,
        private InvoiceCustomerDetailsResolver $customerDetails,
    ) {}

    /**
     * @param  array{invoice_id: string, enabled_payments?: list<string>, credit_card?: array<string, mixed>, customer_details?: array<string, mixed>}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    public function createSnapToken(array $input): array
    {
        return DecryptSourceContext::run('payment.midtrans.checkout_snap', fn (): array => $this->createSnapTokenWithinContext($input));
    }

    /**
     * @param  array{invoice_id: string, enabled_payments?: list<string>, credit_card?: array<string, mixed>, customer_details?: array<string, mixed>}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    private function createSnapTokenWithinContext(array $input): array
    {
        $invoiceModel = $this->invoices->findOrFailById($input['invoice_id']);
        $this->checkoutCancellation->abandonActiveCheckout($invoiceModel);

        $invoice = $this->gatewayResolver->assignGatewayIfMissing(
            $invoiceModel->refresh(),
            'midtrans',
            $input['payment_gateway_id'] ?? null,
        );

        $guardResult = $this->initiationGuard->ensurePayableForProvider($invoice->id, 'midtrans', rejectActiveCheckout: true);
        if (is_array($guardResult)) {
            return $guardResult;
        }
        $invoice = $guardResult;

        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $options = [];

        if (isset($input['enabled_payments'])) {
            $options['enabled_payments'] = $input['enabled_payments'];
        }

        if (isset($input['credit_card'])) {
            $options['credit_card'] = $input['credit_card'];
        }

        if (isset($input['customer_details'])) {
            $options['customer_details'] = $input['customer_details'];
        }

        $params = $this->snapParameterBuilder->build($invoice, $options);

        $invoice->gateway_request_payload = $params;

        try {
            $snap = Snap::createTransaction($params);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        $invoice->gateway_response_payload = json_decode(json_encode($snap), true);
        $this->invoices->save($invoice);

        $clientKey = $gateway->gatewayConfig()['client_key'] ?? null;

        return [
            'ok' => true,
            'data' => [
                'snap_token' => $snap->token ?? null,
                'redirect_url' => $snap->redirect_url ?? null,
                'client_key' => $clientKey,
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->invoice_number,
            ],
            'message_key' => 'api/payments.midtrans.snap_created',
            'http' => 201,
        ];
    }

    /**
     * @param  array{invoice_id: string, charge: array<string, mixed>}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    public function charge(array $input): array
    {
        return DecryptSourceContext::run('payment.midtrans.checkout_charge', fn (): array => $this->chargeWithinContext($input));
    }

    /**
     * @param  array{invoice_id: string, charge: array<string, mixed>}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int}
     */
    private function chargeWithinContext(array $input): array
    {
        $guardResult = $this->initiationGuard->ensurePayableForProvider($input['invoice_id'], 'midtrans');
        if (is_array($guardResult)) {
            return $guardResult;
        }
        $invoice = $guardResult;

        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 503];
        }

        $gross = $this->midtransGrossAmount($invoice);
        $base = [
            'transaction_details' => [
                'order_id' => $invoice->invoice_number,
                'gross_amount' => $gross,
            ],
            'customer_details' => $this->customerDetails->forMidtrans(
                $invoice,
                $input['charge']['customer_details'] ?? null,
            ),
        ];

        $merged = array_replace_recursive($base, $input['charge']);

        $invoice->gateway_request_payload = $merged;

        try {
            $result = CoreApi::charge($merged);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        $invoice->gateway_response_payload = json_decode(json_encode($result), true);

        if (isset($result->transaction_id)) {
            $invoice->external_transaction_id = (string) $result->transaction_id;
        }

        $this->invoices->save($invoice);

        return [
            'ok' => true,
            'data' => json_decode(json_encode($result), true),
            'message_key' => 'api/payments.midtrans.charge_created',
            'http' => 201,
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function getTransactionStatus(string $orderId): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $status = Transaction::status($orderId);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => json_decode(json_encode($status), true),
            'message_key' => 'api/payments.midtrans.status_retrieved',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function cancelTransaction(string $orderId): array
    {
        return $this->transactionAction($orderId, fn () => Transaction::cancel($orderId), 'cancel');
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function expireTransaction(string $orderId): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $result = Transaction::expire($orderId);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => json_decode(json_encode($result), true),
            'message_key' => 'api/payments.midtrans.expire_ok',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function refundTransaction(string $orderId, ?int $amount): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $refundBody = $amount !== null
                ? ['amount' => $amount]
                : false;

            $result = Transaction::refund($orderId, $refundBody);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => json_decode(json_encode($result), true),
            'message_key' => 'api/payments.midtrans.refund_ok',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function approveTransaction(string $orderId): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $code = Transaction::approve($orderId);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => ['status_code' => $code],
            'message_key' => 'api/payments.midtrans.approve_ok',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function denyTransaction(string $orderId): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $result = Transaction::deny($orderId);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        return [
            'ok' => true,
            'data' => json_decode(json_encode($result), true),
            'message_key' => 'api/payments.midtrans.deny_ok',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    public function syncInvoiceStatus(Invoice $invoice): array
    {
        return DecryptSourceContext::run('payment.midtrans.sync_status', fn (): array => $this->syncInvoiceStatusWithinContext($invoice));
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    private function syncInvoiceStatusWithinContext(Invoice $invoice): array
    {
        $this->assertMidtransInvoice($invoice);

        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $status = Transaction::status($invoice->invoice_number);
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        try {
            $updated = $this->synchronizer->sync($invoice, $status);
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
                'midtrans' => json_decode(json_encode($status), true),
            ],
            'message_key' => 'api/payments.midtrans.sync_ok',
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message_key: string}|array{ok: false, message: string, http: int}
     */
    protected function transactionAction(string $orderId, callable $fn, string $label): array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
            $result = $fn();
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'http' => 502];
        }

        if (is_string($result)) {
            return [
                'ok' => true,
                'data' => ['status_code' => $result],
                'message_key' => 'api/payments.midtrans.'.$label.'_ok',
            ];
        }

        return [
            'ok' => true,
            'data' => json_decode(json_encode($result), true),
            'message_key' => 'api/payments.midtrans.'.$label.'_ok',
        ];
    }

    protected function assertMidtransInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('paymentGateway');

        if ($invoice->paymentGateway === null || $invoice->paymentGateway->name !== 'midtrans') {
            abort(422, __('api/payments.midtrans.invoice_not_midtrans'));
        }
    }

    protected function assertInvoicePayable(Invoice $invoice): void
    {
        if ($invoice->status !== 'pending' && $invoice->status !== 'challenge') {
            abort(422, __('api/payments.midtrans.invoice_not_payable'));
        }
    }

    private function midtransGrossAmount(Invoice $invoice): int|float
    {
        $currency = strtoupper((string) ($invoice->currency ?: 'IDR'));
        $gross = (float) $invoice->customer_gross_total;

        if ($currency === 'IDR') {
            return (int) round($gross, 0);
        }

        return round($gross, 2);
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
