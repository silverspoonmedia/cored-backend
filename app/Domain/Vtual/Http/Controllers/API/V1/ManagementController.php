<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\AssignCreatorRequest;
use App\Domain\Vtual\Http\Requests\StoreManagementRequest;
use App\Domain\Vtual\Http\Requests\UpdateManagementRequest;
use App\Domain\Vtual\Http\Resources\ManagementResource;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementService $managements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Management::class);

        return $this->successResponse(
            $this->resolveResource(ManagementResource::collection($this->managements->listForUser($request->user()))),
            __('api/vtual.management.list_success'),
        );
    }

    public function store(StoreManagementRequest $request): JsonResponse
    {
        $this->authorize('create', Management::class);

        $management = $this->managements->create($request->user(), $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($management)),
            __('api/vtual.management.created'),
            201,
        );
    }

    public function show(Request $request, Management $management): JsonResponse
    {
        $this->authorize('view', $management);

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($loaded)),
            __('api/vtual.management.show_success'),
        );
    }

    public function update(UpdateManagementRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $updated = $this->managements->update($request->user(), $management->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($updated)),
            __('api/vtual.management.updated'),
        );
    }

    public function assignCreator(AssignCreatorRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $assignment = $this->managements->assignCreator(
            $request->user(),
            $management->id,
            $request->validated('creator_id'),
        );

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            [
                'assignment_id' => $assignment->id,
                'management' => $this->resolveResource(new ManagementResource($loaded)),
            ],
            __('api/vtual.management.creator_assigned'),
        );
    }

    public function unassignCreator(Request $request, Management $management, string $creator): JsonResponse
    {
        $this->authorize('update', $management);

        $this->managements->unassignCreator($request->user(), $management->id, $creator);

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($loaded)),
            __('api/vtual.management.creator_unassigned'),
        );
    }
}
