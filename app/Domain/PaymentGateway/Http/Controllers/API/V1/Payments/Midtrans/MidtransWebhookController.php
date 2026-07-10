<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Midtrans;

use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransAccountLinkingWebhookService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransNotificationPayloadType;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentNotificationService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private MidtransPaymentNotificationService $paymentNotification,
        private MidtransAccountLinkingWebhookService $accountLinkingWebhook
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return ApiResponder::success(null, __('api/payments.midtrans.webhook_ping'));
        }

        $payload = $this->decodeJsonPayload($request);

        if ($payload === []) {
            return ApiResponder::error(__('api/payments.midtrans.webhook_invalid_json'), 400);
        }

        if (MidtransNotificationPayloadType::isAccountLinking($payload)) {
            return $this->accountLinkingWebhook->handle($request);
        }

        if (MidtransNotificationPayloadType::isSubscription($payload)) {
            Log::info('Midtrans subscription/recurring notification received', [
                'event_name' => $payload['event_name'] ?? null,
                'subscription_id' => $payload['subscription']['id'] ?? $payload['id'] ?? null,
            ]);

            return ApiResponder::success(null, __('api/payments.midtrans.webhook_subscription_ack'), 200);
        }

        return $this->paymentNotification->handle($payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonPayload(Request $request): array
    {
        $raw = $request->getContent();

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
