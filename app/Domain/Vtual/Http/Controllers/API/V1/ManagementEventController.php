<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreManagementEventRequest;
use App\Domain\Vtual\Http\Resources\ManagementEventResource;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementEventService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\VtualEventQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementEventController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementEventService $events,
        private readonly VtualEventQueryRowActions $queryRowActions,
    ) {}

    /**
     * Cross-management list for the standalone SPA page. Staff sees all;
     * org members see their orgs' events (scoped in the service).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $this->events->listForActor($request->user(), $perPage, $page);

        return $this->successResponse([
            'items' => QueryRowActionAttacher::attach(
                $this->resolveResource(
                    ManagementEventResource::collection($list['items'])
                ),
                $list['items'],
                $this->queryRowActions,
                $request->user(),
            ),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $list['total'],
                'has_more' => $page * $perPage < $list['total'],
            ],
        ], __('api/vtual.management.events_list_success'));
    }

    /**
     * Per-management list (org member or staff read path).
     */
    public function managementIndex(Request $request, Management $management): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $this->events->listForManagement($request->user(), $management->id, $perPage, $page);

        return $this->successResponse([
            'items' => $this->resolveResource(
                ManagementEventResource::collection($list['items'])
            ),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $list['total'],
                'has_more' => $page * $perPage < $list['total'],
            ],
        ], __('api/vtual.management.events_list_success'));
    }

    public function store(StoreManagementEventRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $event = $this->events->create($request->user(), $management->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementEventResource($event)),
            __('api/vtual.management.event_created'),
            201,
        );
    }

    public function destroy(Request $request, Management $management, string $event): JsonResponse
    {
        $cancelled = $this->events->cancel($request->user(), $management->id, $event);

        return $this->successResponse(
            $this->resolveResource(new ManagementEventResource($cancelled)),
            __('api/vtual.management.event_cancelled'),
        );
    }
}
