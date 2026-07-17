<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web payment channel management — a pure consumer of the versioned API.
 */
class PaymentChannelController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        $response = $this->api()->get('payment-channels', ['scope' => 'admin']);

        return $this->webView('payment-channels.index', [
            'channels' => $response->successful() ? $response->json('data', []) : [],
            'loadError' => $response->successful() ? null : $this->apiMessage($response),
        ]);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $response = $this->api()->put(
            "payment-channels/{$code}",
            $this->apiPayload($request, ['is_active']),
        );

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->apiMessage($response, __('web/payments.channels.updated')),
            'data' => $response->json('data', []),
        ]);
    }

    public function updateRoute(Request $request, string $route): JsonResponse
    {
        $response = $this->api()->put(
            "payment-channel-routes/{$route}",
            $this->apiPayload($request, ['is_active', 'priority']),
        );

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->apiMessage($response, __('web/payments.channels.route_updated')),
            'data' => $response->json('data', []),
        ]);
    }
}
