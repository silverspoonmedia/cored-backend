<?php

namespace App\Support\Product\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProductActivationController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('admin/product-activations/products');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('product-activations.index', [
            'products' => collect($response->json('data', [])),
        ]);
    }

    public function show(Request $request, string $productKey): View|RedirectResponse
    {
        $response = $this->api()->get("admin/product-activations/products/{$productKey}", $request->only(['state']));

        if (! $response->successful()) {
            return redirect()->route('product-activations.index')
                ->with('error', $this->apiMessage($response));
        }

        $payload = $response->json('data', []);

        return $this->webView('product-activations.show', [
            'productKey' => $productKey,
            'summary' => $payload['summary'] ?? [],
            'activations' => collect($payload['activations'] ?? []),
            'pagination' => $payload['pagination'] ?? [],
            'stateFilter' => $request->string('state', '')->toString(),
        ]);
    }

    public function suspend(Request $request, string $productKey, string $id): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/product-activations/{$id}/suspend", $request->only(['reason']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-activations.show', $productKey)
                ->with('success', __('product-activations.suspended')),
            __('product-activations.suspended'),
        );
    }

    public function reactivate(Request $request, string $productKey, string $id): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/product-activations/{$id}/reactivate");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-activations.show', $productKey)
                ->with('success', __('product-activations.reactivated')),
            __('product-activations.reactivated'),
        );
    }

    public function approve(Request $request, string $productKey, string $id): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/product-activations/{$id}/approve", $request->only(['payload']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-activations.show', $productKey)
                ->with('success', __('product-activations.approved')),
            __('product-activations.approved'),
        );
    }

    public function reject(Request $request, string $productKey, string $id): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/product-activations/{$id}/reject", $request->only(['reason']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-activations.show', $productKey)
                ->with('success', __('product-activations.rejected')),
            __('product-activations.rejected'),
        );
    }
}
