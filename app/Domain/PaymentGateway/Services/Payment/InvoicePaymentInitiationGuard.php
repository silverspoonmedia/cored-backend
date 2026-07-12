<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuInvoiceSynchronizer;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuSdk;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransSdk;
use Illuminate\Support\Facades\DB;
use Midtrans\Transaction;

/**
 * Locks the invoice row, polls the PSP when applicable, syncs local state, then
 * asserts the invoice may still start a new payment session.
 */
class InvoicePaymentInitiationGuard
{
    private const IPAYMU_STATUS_PENDING = 0;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private MidtransInvoiceSynchronizer $midtransSynchronizer,
        private IpaymuInvoiceSynchronizer $ipaymuSynchronizer,
    ) {}

    /**
     * @return Invoice|array{ok: false, message: string, http: int}
     */
    public function ensurePayableForMidtrans(string $invoiceId): Invoice|array
    {
        return $this->ensurePayableForProvider($invoiceId, 'midtrans');
    }

    /**
     * @return Invoice|array{ok: false, message: string, http: int}
     */
    public function ensurePayableForIpaymu(string $invoiceId, bool $rejectActiveCheckout = false): Invoice|array
    {
        return $this->ensurePayableForProvider($invoiceId, 'ipaymu', $rejectActiveCheckout);
    }

    /**
     * @return Invoice|array{ok: false, message: string, http: int}
     */
    public function ensurePayableForProvider(
        string $invoiceId,
        string $providerName,
        bool $rejectActiveCheckout = false,
    ): Invoice|array {
        $activeCheckoutPending = false;

        $transactionResult = DB::transaction(function () use ($invoiceId, $providerName, $rejectActiveCheckout, &$activeCheckoutPending): Invoice|array {
            $invoice = $this->invoices->findOrFailByIdForUpdate($invoiceId);
            $this->assertInvoicePayable($invoice, $providerName);

            $invoice->loadMissing('paymentGateway');

            if ($providerName === 'midtrans') {
                if ($invoice->paymentGateway !== null && $invoice->paymentGateway->name !== 'midtrans') {
                    abort(422, __('api/payments.midtrans.invoice_not_midtrans'));
                }

                $syncError = $this->syncMidtransFromGateway($invoice);
                if (is_array($syncError)) {
                    return $syncError;
                }

                return $invoice->refresh();
            }

            if ($providerName === 'ipaymu') {
                if ($invoice->paymentGateway !== null && $invoice->paymentGateway->name !== 'ipaymu') {
                    abort(422, __('api/payments.ipaymu.invoice_not_ipaymu'));
                }

                if ($this->hasExternalTransactionId($invoice)) {
                    $result = $this->syncIpaymuFromGateway($invoice, $rejectActiveCheckout, $activeCheckoutPending);
                    if (is_array($result)) {
                        return $result;
                    }

                    return $result->refresh();
                }

                return $invoice;
            }

            return $invoice;
        });

        if (is_array($transactionResult)) {
            return $transactionResult;
        }

        if ($activeCheckoutPending) {
            abort(422, __('api/payments.ipaymu.active_payment_exists'));
        }

        $this->assertInvoicePayable($transactionResult, $providerName);

        return $transactionResult;
    }

    /**
     * @return null|array{ok: false, message: string, http: int}
     */
    private function syncMidtransFromGateway(Invoice $invoice): ?array
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return $this->externalSyncFailed('midtrans', $e->getMessage());
        }

        try {
            $status = $this->fetchMidtransTransactionStatus($invoice->invoice_number);
        } catch (\Exception $e) {
            if ($this->isTransactionNotFound($e)) {
                return null;
            }

            return $this->externalSyncFailed('midtrans', $e->getMessage());
        }

        try {
            $this->midtransSynchronizer->sync($invoice, $status);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return null;
    }

    /**
     * @return Invoice|array{ok: false, message: string, http: int}
     */
    private function syncIpaymuFromGateway(Invoice $invoice, bool $rejectActiveCheckout, bool &$activeCheckoutPending): Invoice|array
    {
        $tid = (int) $invoice->external_transaction_id;

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return $this->externalSyncFailed('ipaymu', $e->getMessage());
        }

        $client = IpaymuSdk::makeClient($gateway);

        try {
            $response = $this->fetchIpaymuTransactionStatus($client, $tid);
        } catch (\Throwable $e) {
            return $this->externalSyncFailed('ipaymu', $e->getMessage());
        }

        if ((int) ($response['Status'] ?? 0) !== 200) {
            $message = (string) ($response['Message'] ?? __('api/payments.ipaymu.external_sync_failed'));

            return $this->externalSyncFailed('ipaymu', $message);
        }

        $ipaymuPaymentStatus = (int) data_get($response, 'Data.Status', self::IPAYMU_STATUS_PENDING);

        try {
            $this->ipaymuSynchronizer->sync($invoice, $response);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $invoice->refresh();

        if ($rejectActiveCheckout
            && $ipaymuPaymentStatus === self::IPAYMU_STATUS_PENDING
            && in_array($invoice->status, ['pending', 'challenge'], true)) {
            $activeCheckoutPending = true;
        }

        return $invoice;
    }

    /**
     * @return array{ok: false, message: string, http: int}
     */
    private function externalSyncFailed(string $gateway, string $detail): array
    {
        $message = __('api/payments.'.$gateway.'.external_sync_failed');

        if ($detail !== '') {
            $message .= ' '.$detail;
        }

        return [
            'ok' => false,
            'message' => $message,
            'http' => 503,
        ];
    }

    private function isTransactionNotFound(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, '404')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'does not exist')
            || str_contains($message, 'not found')
            || str_contains($message, 'transaction not found');
    }

    private function hasExternalTransactionId(Invoice $invoice): bool
    {
        $tid = $invoice->external_transaction_id;

        return $tid !== null && $tid !== '';
    }

    private function assertInvoicePayable(Invoice $invoice, string $gateway): void
    {
        if ($invoice->status !== 'pending' && $invoice->status !== 'challenge') {
            abort(422, __('api/payments.'.$gateway.'.invoice_not_payable'));
        }
    }

    /**
     * @throws \Exception
     */
    protected function fetchMidtransTransactionStatus(string $invoiceNumber): object
    {
        return Transaction::status($invoiceNumber);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    protected function fetchIpaymuTransactionStatus(\iPaymu\iPaymu $client, int $transactionId): array
    {
        $response = $client->checkTransaction($transactionId);

        if (! is_array($response)) {
            throw new \RuntimeException('Invalid iPaymu checkTransaction response.');
        }

        return $response;
    }
}
