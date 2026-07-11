<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use Illuminate\Support\Facades\DB;

class InvoiceActiveCheckoutCancellationService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoicePaymentInitiationGuard $initiationGuard,
    ) {}

    public function prepareForChannelCheckout(Invoice $invoice): void
    {
        $this->abandonActiveCheckout($invoice);
    }

    public function abandonActiveCheckout(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $locked = $this->invoices->findOrFailByIdForUpdate($invoice->id);

            if (! $this->hasCheckoutSession($locked)) {
                return;
            }

            $this->cancelActiveSession($locked);
        });

        $invoice->refresh();
    }

    public function hasCheckoutSession(Invoice $invoice): bool
    {
        if ($this->hasExternalTransactionId($invoice)) {
            return true;
        }

        if ($invoice->payment_channel_code !== null && $invoice->payment_channel_code !== '') {
            return true;
        }

        return $this->hasGatewayResponsePayload($invoice);
    }

    private function cancelActiveSession(Invoice $invoice): void
    {
        $invoice->loadMissing('paymentGateway');

        $gatewayName = $invoice->paymentGateway?->name;

        if ($gatewayName === 'midtrans') {
            if ($this->hasExternalTransactionId($invoice) || $this->hasGatewayResponsePayload($invoice)) {
                app(MidtransPaymentService::class)->cancelTransaction($invoice->invoice_number);
                $this->initiationGuard->ensurePayableForProvider($invoice->id, 'midtrans');
                $invoice->refresh();
            }
        }

        if ($gatewayName === 'ipaymu' && $this->hasExternalTransactionId($invoice)) {
            $guardResult = $this->initiationGuard->ensurePayableForProvider($invoice->id, 'ipaymu');

            if (is_array($guardResult)) {
                abort($guardResult['http'], $guardResult['message']);
            }

            $invoice = $guardResult->refresh();

            if ($this->hasExternalTransactionId($invoice) && in_array($invoice->status, ['pending', 'challenge'], true)) {
                abort(422, __('api/payments.checkout.active_session_pending'));
            }
        }

        $this->resetCheckoutFields($invoice);
    }

    private function resetCheckoutFields(Invoice $invoice): void
    {
        $invoice->external_transaction_id = null;
        $invoice->payment_channel_code = null;
        $invoice->gateway_request_payload = null;
        $invoice->gateway_response_payload = null;
        $invoice->payment_gateway_id = null;
        $this->invoices->save($invoice);
    }

    private function hasExternalTransactionId(Invoice $invoice): bool
    {
        $tid = $invoice->external_transaction_id;

        return $tid !== null && $tid !== '';
    }

    private function hasGatewayResponsePayload(Invoice $invoice): bool
    {
        $payload = $invoice->gateway_response_payload;

        if ($payload === null) {
            return false;
        }

        if (is_array($payload)) {
            return $payload !== [];
        }

        return true;
    }
}
