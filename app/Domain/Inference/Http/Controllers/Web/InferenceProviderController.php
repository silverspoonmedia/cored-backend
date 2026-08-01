<?php

namespace App\Domain\Inference\Http\Controllers\Web;

use App\Domain\Inference\Http\Controllers\Web\Concerns\MapsInferenceDatatableActions;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InferenceProviderController extends Controller
{
    use ConsumesApi;
    use MapsInferenceDatatableActions;

    public function index(): View
    {
        return $this->webView('inference.providers.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('inference/providers', array_merge(
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

        $payload = $response->json();
        $payload['data'] = array_map(
            fn (array $row): array => $this->mapInferenceRowActions($row, [
                'show' => 'inference.providers.show',
                'edit' => 'inference.providers.edit',
                'delete' => 'inference.providers.destroy',
            ]),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    public function create(): View
    {
        return $this->webView('inference.providers.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('inference/providers', $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.providers.index')
                ->with('success', __('api/inference.providers.created')),
            __('api/inference.providers.created'),
        );
    }

    public function show(string $provider): View|RedirectResponse
    {
        $providerResponse = $this->api()->get("inference/providers/{$provider}");
        $cardsResponse = $this->api()->get('inference/model-cards');

        if (! $providerResponse->successful()) {
            return redirect()->route('inference.providers.index')->with('error', $this->apiMessage($providerResponse));
        }

        return $this->webView('inference.providers.show', [
            'provider' => $providerResponse->json('data', []),
            'modelCards' => $cardsResponse->successful() ? $cardsResponse->json('data', []) : [],
            'syncRatesUrl' => route('inference.providers.sync-rates', $provider),
        ]);
    }

    public function edit(string $provider): View|RedirectResponse
    {
        $response = $this->api()->get("inference/providers/{$provider}");

        if (! $response->successful()) {
            return redirect()->route('inference.providers.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('inference.providers.edit', [
            'provider' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("inference/providers/{$provider}", $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.providers.index')
                ->with('success', __('api/inference.providers.updated')),
            __('api/inference.providers.updated'),
        );
    }

    public function syncRates(Request $request, string $provider): JsonResponse
    {
        $response = $this->api()->put("inference/providers/{$provider}/rates", [
            'rates' => $request->input('rates', []),
        ]);

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }

    public function destroy(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("inference/providers/{$provider}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.providers.index')
                ->with('success', __('api/inference.providers.deleted')),
            __('api/inference.providers.deleted'),
        );
    }
}
