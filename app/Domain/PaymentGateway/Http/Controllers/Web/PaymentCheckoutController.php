<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutGatewayResolver;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\ResolvesPaymentCheckoutContext;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies checkout initiation to the versioned payment APIs.
 *
 * Blade checkout JS calls these web routes; the controller reads the JWT from
 * the HttpOnly cookie and forwards to `/api/v1/*`.
 */
class PaymentCheckoutController extends Controller
{
    use ConsumesApi;
    use ResolvesPaymentCheckoutContext;

    public function __construct(
        private InvoiceCheckoutGatewayResolver $gatewayResolver,
    ) {}

    public function createSnapToken(Request $request, string $invoice): JsonResponse
    {
        $invoiceData = $this->fetchInvoiceData($invoice);

        if ($invoiceData instanceof JsonResponse) {
            return $invoiceData;
        }

        $provider = $this->gatewayResolver->resolveProviderNameForInvoice($invoiceData);

        if (! in_array($provider, ['midtrans', 'ipaymu'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => __('web/payments.checkout.unsupported_gateway'),
            ], 422);
        }

        $payload = ['invoice_id' => $invoice];

        if ($request->filled('return_url')) {
            $payload['return_url'] = $request->string('return_url')->toString();
        }

        if ($request->filled('cancel_url')) {
            $payload['cancel_url'] = $request->string('cancel_url')->toString();
        }

        $response = $this->api()->post("payments/providers/{$provider}/snap/token", $payload);

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->apiMessage($response, __('web/payments.checkout.started')),
            'data' => $response->json('data', []),
            'provider' => $provider,
        ], $response->status());
    }

    public function createChannelCheckout(Request $request, string $invoice): JsonResponse
    {
        $response = $this->api()->post(
            "invoices/{$invoice}/channel-checkout",
            $this->apiPayload($request, ['channel_code']),
        );

        if (! $response->successful()) {
            $payload = [
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ];

            if ($response->json('data.snap_fallback_available') === true) {
                $payload['data'] = [
                    'snap_fallback_available' => true,
                    'failed_routes' => $response->json('data.failed_routes', 0),
                ];
            }

            return response()->json($payload, $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->apiMessage($response, __('web/payments.checkout.channel_ready')),
            'data' => $response->json('data', []),
        ], $response->status());
    }

    public function syncInvoiceStatus(string $invoice): JsonResponse
    {
        $invoiceData = $this->fetchInvoiceData($invoice);

        if ($invoiceData instanceof JsonResponse) {
            return $invoiceData;
        }

        $provider = $this->gatewayResolver->resolveProviderNameForInvoice($invoiceData);

        if (! in_array($provider, ['midtrans', 'ipaymu'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => __('web/payments.checkout.unsupported_gateway'),
            ], 422);
        }

        $response = $this->api()->post("payments/providers/{$provider}/invoices/{$invoice}/sync-status");

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->apiMessage($response, __('web/payments.checkout.synced')),
            'data' => $response->json('data', []),
        ], $response->status());
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function fetchInvoiceData(string $invoice): array|JsonResponse
    {
        $invoiceResponse = $this->api()->get("invoices/{$invoice}");

        if (! $invoiceResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($invoiceResponse),
            ], $invoiceResponse->status() >= 400 ? $invoiceResponse->status() : 400);
        }

        return $invoiceResponse->json('data', []);
    }

    public function checkoutChannels(string $invoice): JsonResponse
    {
        $response = $this->api()->get("invoices/{$invoice}/checkout-channels");

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }
}
