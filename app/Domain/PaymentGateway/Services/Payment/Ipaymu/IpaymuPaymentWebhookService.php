<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\ApiResponder;
use App\Support\Encryption\DecryptSourceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IpaymuPaymentWebhookService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private IpaymuInvoiceSynchronizer $synchronizer
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return ApiResponder::success(null, __('api/payments.ipaymu.webhook_ping'));
        }

        $raw = $request->getContent();

        return DecryptSourceContext::run('payment.ipaymu.webhook', function () use ($request, $raw): JsonResponse {
            return $this->handleWebhookPayload($request, $raw);
        });
    }

    private function handleWebhookPayload(Request $request, string $raw): JsonResponse
    {
        if ($raw === '') {
            return ApiResponder::error(__('api/payments.ipaymu.webhook_invalid_json'), 400);
        }

        $payload = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload)) {
            return ApiResponder::error(__('api/payments.ipaymu.webhook_invalid_json'), 400);
        }

        $gateway = IpaymuSdk::resolveGateway();

        try {
            IpaymuSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ApiResponder::error($e->getMessage(), 503);
        }

        $cfg = $gateway->gatewayConfig();
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $expectedVa = trim((string) ($cfg['va'] ?? ''));

        $vaHeader = trim((string) ($request->header('va') ?? ''));

        if ($expectedVa !== '' && $vaHeader !== '' && $vaHeader !== $expectedVa) {
            Log::notice('iPaymu webhook: va header differs from configured VA (legacy path may still verify)', [
                'header_va' => $vaHeader,
            ]);
        }

        if (! self::webhookSignatureValid($request, $raw, $payload, $apiKey, $expectedVa, $vaHeader)) {
            Log::warning('iPaymu webhook: invalid signature', [
                'reference_id' => IpaymuWebhookPayload::referenceId($payload),
                'transaction_id' => IpaymuWebhookPayload::transactionId($payload),
                'has_va_header' => $vaHeader !== '',
                'has_x_signature' => trim((string) ($request->header('X-Signature') ?? '')) !== '',
                'has_legacy_signature' => trim((string) ($request->header('signature') ?? '')) !== '',
            ]);

            return ApiResponder::error(__('api/payments.ipaymu.webhook_invalid_signature'), 401);
        }

        $referenceId = IpaymuWebhookPayload::referenceId($payload);
        $transactionId = IpaymuWebhookPayload::transactionId($payload);

        $invoice = null;

        if (is_string($referenceId) && $referenceId !== '') {
            $invoice = $this->invoices->findByInvoiceNumberForGateway($referenceId, 'ipaymu');
        }

        if ($invoice === null && is_string($transactionId) && $transactionId !== '') {
            $invoice = $this->invoices->findByExternalTransactionIdForGateway($transactionId, 'ipaymu');
        }

        if ($invoice === null) {
            Log::notice('iPaymu webhook: invoice not found', [
                'reference_id' => $referenceId,
                'transaction_id' => $transactionId,
            ]);

            return ApiResponder::success(null, __('api/payments.ipaymu.webhook_orphan'), 200);
        }

        if ($transactionId === null || $transactionId === '') {
            Log::notice('iPaymu webhook: missing transaction id in payload', ['reference_id' => $referenceId]);

            return ApiResponder::success(null, __('api/payments.ipaymu.webhook_ack_no_status'), 200);
        }

        $client = IpaymuSdk::makeClient($gateway);

        try {
            $status = $client->checkTransaction((int) $transactionId);
        } catch (\Throwable $e) {
            Log::warning('iPaymu webhook: checkTransaction failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponder::success(null, __('api/payments.ipaymu.webhook_ack_no_status'), 200);
        }

        if (! is_array($status) || (int) ($status['Status'] ?? 0) !== 200) {
            Log::notice('iPaymu webhook: checkTransaction returned non-200', [
                'transaction_id' => $transactionId,
                'body' => $status,
            ]);

            return ApiResponder::success(null, __('api/payments.ipaymu.webhook_ack_no_status'), 200);
        }

        try {
            $this->synchronizer->sync($invoice, $status);
        } catch (\RuntimeException $e) {
            $data = is_array($status['Data'] ?? null) ? $status['Data'] : [];
            Log::warning('iPaymu webhook sync rejected', [
                'transaction_id' => $transactionId,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_subtotal' => $invoice->subtotal,
                'invoice_fee' => $invoice->payment_channel_fee,
                'invoice_gross' => $invoice->customer_gross_total,
                'ipaymu_subtotal' => $data['SubTotal'] ?? $data['sub_total'] ?? null,
                'ipaymu_fee' => $data['Fee'] ?? $data['fee'] ?? null,
                'ipaymu_amount' => $data['Amount'] ?? $data['amount'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return ApiResponder::error($e->getMessage(), 422);
        }

        return ApiResponder::success(null, __('api/payments.ipaymu.webhook_ok'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function webhookSignatureValid(
        Request $request,
        string $raw,
        array $payload,
        string $apiKey,
        string $expectedVa,
        string $vaHeader
    ): bool {
        $xSignature = trim((string) ($request->header('X-Signature') ?? ''));

        if ($xSignature !== '' && $expectedVa !== '') {
            if (IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac($payload, $xSignature, $expectedVa)) {
                return true;
            }
        }

        $legacySignature = trim((string) ($request->header('signature') ?? ''));

        if ($legacySignature !== '') {
            return self::signatureValidForAnyVa($raw, $legacySignature, $apiKey, $vaHeader, $expectedVa);
        }

        return false;
    }

    protected static function signatureValidForAnyVa(
        string $rawBody,
        string $signature,
        string $apiKey,
        string $vaHeader,
        string $expectedVa
    ): bool {
        $vaCandidates = [];

        if ($vaHeader !== '') {
            $vaCandidates[] = $vaHeader;
        }

        if ($expectedVa !== '' && ! in_array($expectedVa, $vaCandidates, true)) {
            $vaCandidates[] = $expectedVa;
        }

        if ($vaCandidates === []) {
            return false;
        }

        foreach ($vaCandidates as $va) {
            if (IpaymuSignatureVerifier::isValid($rawBody, $va, $signature, $apiKey)) {
                return true;
            }
        }

        return false;
    }
}
