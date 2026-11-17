<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Internal
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Class
class MidtransAccountLinkingWebhookService
{
    /**
     * Account linking / unlinking notification: verify X-SIGNATURE (RSA-SHA256) then return Open API ACK.
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return MidtransAccountLinkingResponder::success();
        }

        if (config('payment.gateways.midtrans.open_api_verify') === false) {
            Log::warning('Midtrans Open API X-SIGNATURE verification is disabled (MIDTRANS_OPEN_API_VERIFY=false).');

            return MidtransAccountLinkingResponder::success();
        }

        $publicKey = $this->resolveOpenApiPublicKey();

        if ($publicKey === null) {
            return $this->errorResponse(__('api/payments.midtrans.open_api_public_key_missing'), 503);
        }

        if (! MidtransOpenApiSignatureVerifier::verify($request, MidtransOpenApiSignatureVerifier::ACCOUNT_LINKING_PATH, $publicKey)) {
            return $this->errorResponse(__('api/payments.midtrans.open_api_signature_invalid'), 401);
        }

        return MidtransAccountLinkingResponder::success();
    }

    protected function resolveOpenApiPublicKey(): ?string
    {
        $key = config('payment.gateways.midtrans.open_api_public_key');

        if (is_string($key) && trim($key) !== '') {
            return trim($key);
        }

        $path = config('payment.gateways.midtrans.open_api_public_key_file');

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return is_string($contents) && trim($contents) !== '' ? trim($contents) : null;
    }

    protected function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }
}
