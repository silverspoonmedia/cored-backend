<?php

namespace App\Domain\Product\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Product items — pure consumer of `/api/v1/product/items`.
 */
class ItemController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('product/items');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('product-items.index', [
            'items' => collect($response->json('data', [])),
        ]);
    }

    public function create(): View
    {
        return $this->webView('product-items.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('product/items', $this->payload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-items.index')
                ->with('success', __('api/product-items.created')),
            __('api/product-items.created'),
        );
    }

    public function edit(string $item): View|RedirectResponse
    {
        $response = $this->api()->get("product/items/{$item}");

        if (! $response->successful()) {
            return redirect()->route('product-items.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('product-items.edit', [
            'item' => (object) $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("product/items/{$item}", $this->payload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-items.index')
                ->with('success', __('api/product-items.updated')),
            __('api/product-items.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $item): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("product/items/{$item}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('product-items.index')
                ->with('success', __('api/product-items.deleted')),
            __('api/product-items.deleted'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $request->only([
            'sku',
            'name',
            'description',
            'unit_price_cents',
            'status',
        ]);
    }
}
