<?php

namespace App\Domain\Service\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Service catalog — pure consumer of `/api/v1/services`.
 */
class ServiceController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('services');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('services.index', [
            'services' => collect($response->json('data', [])),
        ]);
    }

    public function create(): View
    {
        return $this->webView('services.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('services', $this->payload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('services.index')
                ->with('success', __('api/services.created')),
            __('api/services.created'),
        );
    }

    public function show(string $service): View|RedirectResponse
    {
        $response = $this->api()->get("services/{$service}");

        if (! $response->successful()) {
            return redirect()->route('services.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('services.show', [
            'service' => $response->json('data', []),
        ]);
    }

    public function edit(string $service): View|RedirectResponse
    {
        $response = $this->api()->get("services/{$service}");

        if (! $response->successful()) {
            return redirect()->route('services.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('services.edit', [
            'service' => (object) $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $service): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("services/{$service}", $this->payload($request));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('services.index')
                ->with('success', __('api/services.updated')),
            __('api/services.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $service): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("services/{$service}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('services.index')
                ->with('success', __('api/services.deleted')),
            __('api/services.deleted'),
        );
    }

    public function checkout(Request $request, string $service): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("services/{$service}/checkout", [
            'currency' => strtoupper((string) $request->input('currency', 'USD')),
        ]);

        if (! $response->successful()) {
            return $this->respondFromApi(
                $request,
                $response,
                fn (): RedirectResponse => redirect()->route('services.show', $service)
                    ->with('error', $this->apiMessage($response)),
                $this->apiMessage($response),
            );
        }

        $purchaseId = $response->json('data.id');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('service-purchases.show', $purchaseId)
                ->with('success', __('api/services.checkout_success')),
            __('api/services.checkout_success'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = $request->only([
            'code',
            'name',
            'description',
            'billing_interval',
            'fulfillment_target',
            'entitlement',
            'status',
        ]);

        $prices = $request->input('prices');

        if (is_array($prices)) {
            $data['prices'] = array_values(array_filter(array_map(
                function (mixed $row): ?array {
                    if (! is_array($row)) {
                        return null;
                    }

                    $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
                    $amount = $row['amount'] ?? null;

                    if ($currency === '' || $amount === null || $amount === '') {
                        return null;
                    }

                    return [
                        'currency' => $currency,
                        'amount' => $amount,
                    ];
                },
                $prices,
            )));
        }

        return $data;
    }
}
