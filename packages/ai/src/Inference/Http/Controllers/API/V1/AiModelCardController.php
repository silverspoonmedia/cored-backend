<?php

namespace App\Domain\Inference\Http\Controllers\API\V1;

use App\Domain\Inference\Http\Requests\IndexAiModelCardRequest;
use App\Domain\Inference\Http\Requests\StoreAiModelCardRequest;
use App\Domain\Inference\Http\Requests\UpdateAiModelCardRequest;
use App\Domain\Inference\Http\Resources\AiModelCardDetailResource;
use App\Domain\Inference\Http\Resources\AiModelCardResource;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Services\InferenceCatalogService;
use App\Domain\Inference\Services\InferenceUsageAnalyticsService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Domain\Inference\Support\ListActions\AiModelCardQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AiModelCardController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly InferenceCatalogService $catalog,
        private readonly InferenceUsageAnalyticsService $analytics,
        private readonly AiModelCardQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexAiModelCardRequest $request): JsonResponse
    {
        $cards = $this->catalog->listModelCards();

        return $this->respondToQueryList(
            $request,
            $cards,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(AiModelCardResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/inference.model_cards.list'),
        );
    }

    public function show(AiModelCard $modelCard): JsonResponse
    {
        abort_unless(request()->user()?->can('view inference catalog'), 403);

        $card = $this->catalog->getModelCard($modelCard->id);
        $providerRate = $this->catalog->providerRateForCard($card);
        $usageSummary = $this->analytics->modelCardSummary($card->id);

        return $this->successResponse(
            $this->resolveResource(new AiModelCardDetailResource($card, $providerRate, $usageSummary)),
            __('api/inference.model_cards.show'),
        );
    }

    public function store(StoreAiModelCardRequest $request): JsonResponse
    {
        $card = $this->catalog->createModelCard($request->validated());

        return $this->successResponse(
            $this->resolveResource(new AiModelCardResource($card)),
            __('api/inference.model_cards.created'),
            201,
        );
    }

    public function update(UpdateAiModelCardRequest $request, AiModelCard $modelCard): JsonResponse
    {
        $card = $this->catalog->updateModelCard($modelCard->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new AiModelCardResource($card)),
            __('api/inference.model_cards.updated'),
        );
    }

    public function destroy(AiModelCard $modelCard): JsonResponse
    {
        abort_unless(request()->user()?->can('manage inference catalog'), 403);

        $this->catalog->deleteModelCard($modelCard->id);

        return $this->successResponse(null, __('api/inference.model_cards.deleted'));
    }
}
