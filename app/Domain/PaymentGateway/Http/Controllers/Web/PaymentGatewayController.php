<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentGatewayController as ApiPaymentGatewayController;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        return $this->webView('payment-gateways.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $response = $this->api()->post('payment-gateways', $this->apiPayload($request, [
            'name', 'display_name', 'is_active', 'priority',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('payment-gateways.index')
                ->with('success', __('api/payments.gateways.created')),
            __('api/payments.gateways.created'),
        );
    }

    public function edit(string $paymentGateway): View|RedirectResponse
    {
        $response = $this->api()->get("payment-gateways/{$paymentGateway}");

        if (! $response->successful()) {
            return redirect()->route('payment-gateways.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('payment-gateways.edit', [
            'gateway' => (object) $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $paymentGateway): RedirectResponse
    {
        $response = $this->api()->put("payment-gateways/{$paymentGateway}", $this->apiPayload($request, [
            'name', 'display_name', 'is_active', 'priority',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('payment-gateways.index')
                ->with('success', __('api/payments.gateways.updated')),
            __('api/payments.gateways.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $paymentGateway): RedirectResponse
    {
        $response = $this->api()->delete("payment-gateways/{$paymentGateway}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('payment-gateways.index')
                ->with('success', __('api/payments.gateways.deleted')),
            __('api/payments.gateways.deleted'),
        );
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
