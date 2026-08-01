<?php

namespace App\Domain\Inference\Http\Controllers\API\V1;

use App\Domain\Inference\Http\Requests\InferenceUsageOverviewRequest;
use App\Domain\Inference\Http\Requests\IndexInferenceUsageEventRequest;
use App\Domain\Inference\Services\InferenceUsageAnalyticsService;
use App\Domain\Microstock\Http\Resources\AdminBillingUsageEventResource;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class InferenceUsageController extends Controller
{
    use ApiResponse;

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

        return $this->successResponse([
            'items' => AdminBillingUsageEventResource::collection($paginator->getCollection()),
            'pagination' => $this->paginationMeta($paginator),
        ], __('api/inference.usage.events'));
    }

    /**
     * @return array<string, int|bool>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
