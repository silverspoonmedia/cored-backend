<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\ApiResponder;
use App\Support\Encryption\DecryptSourceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Midtrans\Transaction;

class MidtransPaymentNotificationService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private MidtransInvoiceSynchronizer $synchronizer
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): JsonResponse
    {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            return ApiResponder::error($e->getMessage(), 503);
        }

        $serverKey = $gateway->gatewayConfig()['server_key'] ?? '';

        if (! MidtransSignatureVerifier::isValid($payload, $serverKey)) {
            return ApiResponder::error(__('api/payments.midtrans.webhook_invalid_signature'), 401);
        }

        $orderId = $payload['order_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            return ApiResponder::error(__('api/payments.midtrans.webhook_missing_order'), 422);
        }

        return DecryptSourceContext::run('payment.midtrans.webhook', function () use ($orderId, $payload): JsonResponse {
            return $this->handleVerifiedNotification($orderId, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleVerifiedNotification(string $orderId, array $payload): JsonResponse
    {
        $status = $this->resolveTransactionStatus($orderId, $payload);

        if ($status === null) {
            Log::notice('Midtrans webhook: could not resolve status (Get Status failed; payload incomplete)', [
                'order_id' => $orderId,
            ]);

            return ApiResponder::success(null, __('api/payments.midtrans.webhook_ack_no_status'), 200);
        }

        $invoice = $this->invoices->findByInvoiceNumberForGateway($orderId, 'midtrans');

        if ($invoice === null) {
            Log::notice('Midtrans webhook: invoice not found', ['order_id' => $orderId]);

            return ApiResponder::success(null, __('api/payments.midtrans.webhook_orphan'), 200);
        }

        try {
            $this->synchronizer->sync($invoice, $status);
        } catch (\RuntimeException $e) {
            Log::warning('Midtrans webhook sync rejected', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return ApiResponder::error($e->getMessage(), 422);
        }

        return ApiResponder::success(null, __('api/payments.midtrans.webhook_ok'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTransactionStatus(string $orderId, array $payload): object|array|null
    {
        $ids = [];

        if (is_string($payload['transaction_id'] ?? null) && $payload['transaction_id'] !== '') {
            $ids[] = $payload['transaction_id'];
        }

        if (! in_array($orderId, $ids, true)) {
            $ids[] = $orderId;
        }

        foreach ($ids as $id) {
            try {
                return Transaction::status($id);
            } catch (\Exception $e) {
                Log::info('Midtrans Transaction::status failed, trying next id', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($payload['transaction_status'])) {
            Log::notice('Midtrans webhook: using signed notification payload after Get Status failure', [
                'order_id' => $orderId,
            ]);

            return $payload;
        }

        return null;
    }
}
