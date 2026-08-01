<?php

namespace App\Domain\Inference\Http\Controllers\Web;

use App\Domain\Inference\Http\Controllers\Web\Concerns\MapsInferenceDatatableActions;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiModelCardController extends Controller
{
    use ConsumesApi;
    use MapsInferenceDatatableActions;

    public function index(): View
    {
        return $this->webView('inference.model-cards.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('inference/model-cards', array_merge(
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
                'show' => 'inference.model-cards.show',
                'edit' => 'inference.model-cards.edit',
                'delete' => 'inference.model-cards.destroy',
            ]),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    public function create(): View
    {
        return $this->webView('inference.model-cards.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('inference/model-cards', $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.model-cards.index')
                ->with('success', __('api/inference.model_cards.created')),
            __('api/inference.model_cards.created'),
        );
    }

    public function show(string $modelCard): View|RedirectResponse
    {
        $response = $this->api()->get("inference/model-cards/{$modelCard}");

        if (! $response->successful()) {
            return redirect()->route('inference.model-cards.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('inference.model-cards.show', [
            'card' => $response->json('data', []),
            'usageEventsUrl' => route('inference.usage-events.api'),
        ]);
    }

    public function edit(string $modelCard): View|RedirectResponse
    {
        $response = $this->api()->get("inference/model-cards/{$modelCard}");

        if (! $response->successful()) {
            return redirect()->route('inference.model-cards.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('inference.model-cards.edit', [
            'card' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $modelCard): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("inference/model-cards/{$modelCard}", $request->except(['_token', '_method']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.model-cards.show', $modelCard)
                ->with('success', __('api/inference.model_cards.updated')),
            __('api/inference.model_cards.updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroy(Request $request, string $modelCard): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("inference/model-cards/{$modelCard}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('inference.model-cards.index')
                ->with('success', __('api/inference.model_cards.deleted')),
            __('api/inference.model_cards.deleted'),
        );
    }
}
