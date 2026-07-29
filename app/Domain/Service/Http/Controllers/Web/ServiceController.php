<?php

namespace App\Domain\Service\Http\Controllers\Web;

use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\MicrostockCatalog;
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

    public function index(Request $request): View|RedirectResponse
    {
        $response = $this->api()->get('services');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        $services = collect($response->json('data', []));

        $microstockFree = $services->firstWhere('code', MicrostockCatalog::CODE_PLAN_FREE);
        $microstockSlot = $services->firstWhere('code', MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT);
        $microstockAiCredits = $services->firstWhere('code', MicrostockCatalog::CODE_ADDON_AI_CREDITS_5);
        $microstockActive = $request->user() !== null
            && app(ServiceEntitlementService::class)->hasActiveMicrostockWorkspaceSlot($request->user()->id);

        $microstockBilling = null;
        if ($microstockActive && $request->user() !== null) {
            $billingResponse = $this->api()->get('microstock/billing');
            if ($billingResponse->successful()) {
                $microstockBilling = $billingResponse->json('data');
            }
        }

        return $this->webView('services.index', [
            'services' => $services,
            'microstockFree' => $microstockFree,
            'microstockSlot' => $microstockSlot,
            'microstockAiCredits' => $microstockAiCredits,
            'microstockActive' => $microstockActive,
            'microstockBilling' => $microstockBilling,
        ]);
    }

    public function create(): View
    {
        return $this->webView('services.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('services', $this->apiPayload($request, [
            'code',
            'name',
            'description',
            'billing_interval',
            'fulfillment_target',
            'entitlement',
            'plan_kind',
            'storage_bytes',
            'status',
            'prices',
        ]));

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
        $response = $this->api()->put("services/{$service}", $this->apiPayload($request, [
            'code',
            'name',
            'description',
            'billing_interval',
            'fulfillment_target',
            'entitlement',
            'plan_kind',
            'storage_bytes',
            'status',
            'prices',
        ]));

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
}
