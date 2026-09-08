<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\IndexOrphanCreatorRequest;
use App\Domain\Vtual\Http\Resources\CreatorResource;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\CreatorService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\OrphanCreatorQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrphanCreatorController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly CreatorService $creators,
        private readonly OrphanCreatorQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexOrphanCreatorRequest $request): JsonResponse
    {
        $actor = $request->user();
        $orphans = $this->creators->listClaimable();

        return $this->respondToQueryList(
            $request,
            $orphans,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(CreatorResource::collection($list)),
                $list,
                $this->queryRowActions,
                $actor,
            ),
            __('api/vtual.orphan.list_success'),
        );
    }

    public function show(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('view', $creator);

        if (! $this->creators->isClaimable($creator)) {
            return $this->errorResponse(__('api/vtual.orphan.not_claimable'), 403);
        }

        $loaded = $creator->loadCount('channels')->load(['channels.platform']);

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($loaded)),
            __('api/vtual.orphan.show_success'),
        );
    }
}
