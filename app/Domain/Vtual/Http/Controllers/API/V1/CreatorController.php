<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\MergeCreatorRequest;
use App\Domain\Vtual\Http\Requests\StoreCreatorRequest;
use App\Domain\Vtual\Http\Requests\UpdateCreatorRequest;
use App\Domain\Vtual\Http\Resources\CreatorResource;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\CreatorMergeService;
use App\Domain\Vtual\Services\CreatorService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\OwnedCreatorQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CreatorService $creators,
        private readonly CreatorMergeService $merge,
        private readonly OwnedCreatorQueryRowActions $ownedQueryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Creator::class);

        return $this->successResponse(
            $this->resolveResource(CreatorResource::collection($this->creators->listForUser($request->user()))),
            __('api/vtual.creator.list_success'),
        );
    }

    public function owned(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Creator::class);

        $list = $this->creators->listOwnedForUser($request->user());

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(CreatorResource::collection($list)),
                $list,
                $this->ownedQueryRowActions,
                $request->user(),
            ),
            __('api/vtual.creator.owned_list_success'),
        );
    }

    public function store(StoreCreatorRequest $request): JsonResponse
    {
        $this->authorize('create', Creator::class);

        $creator = $this->creators->create($request->user(), $request->validated());

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($creator)),
            __('api/vtual.creator.created'),
            201,
        );
    }

    /**
     * Staff-only: seed orphan creator (no owner membership) for later claim.
     */
    public function storeOrphan(StoreCreatorRequest $request): JsonResponse
    {
        $this->authorize('create', Creator::class);

        $creator = $this->creators->createOrphan($request->user(), $request->validated());

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($creator)),
            __('api/vtual.creator.orphan_created'),
            201,
        );
    }

    public function show(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('view', $creator);

        $loaded = $this->creators->show($request->user(), $creator->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($loaded)),
            __('api/vtual.creator.show_success'),
        );
    }

    public function update(UpdateCreatorRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('update', $creator);

        $updated = $this->creators->update($request->user(), $creator->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($updated)),
            __('api/vtual.creator.updated'),
        );
    }

    public function destroy(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('delete', $creator);

        $archived = $this->creators->archive($request->user(), $creator->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($archived)),
            __('api/vtual.creator.archived'),
        );
    }

    /**
     * Absorb source profile into destination (owner on both, or manage any).
     */
    public function merge(MergeCreatorRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('update', $creator);

        $destinationId = (string) $request->validated('destination_id');
        $destination = $this->merge->merge($request->user(), $creator->id, $destinationId);

        return $this->successResponse(
            $this->resolveResource(new CreatorResource($destination)),
            __('api/vtual.merge.success'),
        );
    }
}
