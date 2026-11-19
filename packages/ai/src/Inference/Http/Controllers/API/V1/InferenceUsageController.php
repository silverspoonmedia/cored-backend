<?php

namespace App\Domain\Inference\Http\Controllers\API\V1;

use App\Domain\Inference\Http\Requests\IndexInferenceUsageEventRequest;
use App\Domain\Inference\Http\Requests\InferenceUsageOverviewRequest;
use App\Domain\Inference\Services\InferenceUsageAnalyticsService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListQueryPaginator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class InferenceUsageController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly InferenceUsageAnalyticsService $analytics,
    ) {}

    public function overview(InferenceUsageOverviewRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->overview(
                $request->query('from'),
                $request->query('to'),
                $request->query('model_slug'),
                $request->query('model_card_id'),
                $request->query('user_id'),
                $request->query('stage'),
                $request->query('inference_provider_id'),
            ),
            __('api/inference.usage.overview'),
        );
    }

    public function usageEvents(IndexInferenceUsageEventRequest $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->analytics->eventsPaginated(
            $perPage,
            $request->query('from'),
            $request->query('to'),
            $request->query('model_slug'),
            $request->query('model_card_id'),
            $request->query('user_id'),
            $request->query('stage'),
            $request->query('inference_provider_id'),
        );

        /** @var class-string<JsonResource> $resource */
        $resource = (string) config('ai.usage_event_resource');

        return $this->successResponse([
            'items' => $this->resolveResource($resource::collection($paginator->getCollection())),
            'pagination' => ListQueryPaginator::meta($paginator),
        ], __('api/inference.usage.events'));
    }
}
