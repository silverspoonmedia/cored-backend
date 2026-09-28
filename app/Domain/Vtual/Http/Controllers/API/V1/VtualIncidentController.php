<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Http\Requests\IndexVtualIncidentRequest;
use App\Domain\Vtual\Http\Requests\UpdateVtualIncidentStatusRequest;
use App\Domain\Vtual\Http\Resources\VtualIncidentResource;
use App\Domain\Vtual\Models\VtualIncident;
use App\Domain\Vtual\Services\VtualIncidentService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\VtualIncidentQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Staff triage queue for vTual integration incidents.
 *
 * Read + triage only. Nothing here creates an incident — the recorder does, from
 * the failure paths themselves — and nothing here deletes one: an incident is
 * the record of an integration having been broken, so it is resolved, not
 * removed.
 */
class VtualIncidentController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly VtualIncidentService $incidents,
        private readonly VtualIncidentQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexVtualIncidentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = $request->wantsQueryPaging()
            ? 500
            : (int) ($validated['limit'] ?? 50);
        $items = $this->incidents->list($validated, $limit);

        return $this->respondToQueryList(
            $request,
            $items,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(VtualIncidentResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/vtual.incident.list_success'),
        );
    }

    public function show(VtualIncident $incident): JsonResponse
    {
        $loaded = $this->incidents->get($incident->id);

        return $this->successResponse(
            $this->resolveResource(new VtualIncidentResource($loaded)),
            __('api/vtual.incident.show_success'),
        );
    }

    public function updateStatus(UpdateVtualIncidentStatusRequest $request, VtualIncident $incident): JsonResponse
    {
        $actorId = $request->user()?->getAuthIdentifier();
        $actorId = is_string($actorId) ? $actorId : null;

        $updated = $this->incidents->updateStatus(
            $incident,
            IncidentStatus::from($request->validated('status')),
            $actorId,
        );

        return $this->successResponse(
            $this->resolveResource(new VtualIncidentResource($updated)),
            __('api/vtual.incident.status_updated'),
        );
    }
}
