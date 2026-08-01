<?php

namespace App\Domain\Inference\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InferenceUsageController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        $cardsResponse = $this->api()->get('inference/model-cards');

        return $this->webView('inference.usage-events.index', [
            'modelCards' => $cardsResponse->successful() ? $cardsResponse->json('data', []) : [],
            'overviewUrl' => route('inference.usage.overview'),
            'eventsUrl' => route('inference.usage-events.api'),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $response = $this->api()->get('inference/usage/overview', $request->query());

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }

    public function usageEvents(Request $request): JsonResponse
    {
        $response = $this->api()->get('inference/usage-events', $request->query());

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }
}
