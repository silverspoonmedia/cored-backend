<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies hosted checkout initiation to the versioned payment provider APIs.
 *
 * Blade checkout JS calls these web routes; the controller reads the JWT from
 * the HttpOnly cookie and forwards to `/api/v1/payments/providers/{provider}/snap/token`.
 */
class PaymentCheckoutController extends Controller
{
    use ConsumesApi;

    public function createSnapToken(Request $request, string $invoice): JsonResponse
    {
        $invoiceResponse = $this->api()->get("invoices/{$invoice}");

        if (! $invoiceResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($invoiceResponse),
            ], $invoiceResponse->status() >= 400 ? $invoiceResponse->status() : 400);
        }

        $invoiceData = $invoiceResponse->json('data', []);
        $provider = (string) data_get($invoiceData, 'payment_gateway.name');

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
}
