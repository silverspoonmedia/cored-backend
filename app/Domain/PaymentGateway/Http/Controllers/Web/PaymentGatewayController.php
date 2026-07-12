<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentGatewayController as ApiPaymentGatewayController;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web payment gateway listing — a pure consumer of the versioned API.
 *
 * Forwards to `/api/v1/payment-gateways` (see {@see ApiPaymentGatewayController})
 * via {@see ConsumesApi} and renders the datatable payload as Blade.
 */
class PaymentGatewayController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('payment-gateways.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('payment-gateways', array_merge(
            $request->query(),
            ['display_mode' => 'datatable'],
        ));

        if (! $response->successful()) {
            return response()->json([
                'draw' => (int) $request->query('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }
}
