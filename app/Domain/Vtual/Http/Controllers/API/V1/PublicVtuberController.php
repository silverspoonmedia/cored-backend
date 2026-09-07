<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\PublicContentResource;
use App\Domain\Vtual\Http\Resources\PublicEventResource;
use App\Domain\Vtual\Http\Resources\PublicVtuberProfileResource;
use App\Domain\Vtual\Http\Resources\PublicVtuberResource;
use App\Domain\Vtual\Services\VtuberProfileService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-safe public vTual directory + profile reads (SPA /vtual landing).
 * No auth — the service layer already filters to active creators with
 * active + public channels only.
 */
class PublicVtuberController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly VtuberProfileService $profiles,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));
        $search = $request->query('search');

        $paginator = $this->profiles->directory(
            is_string($search) ? $search : null,
            $perPage,
        );

        return $this->successResponse([
            'items' => $this->resolveResource(
                PublicVtuberResource::collection($paginator->getCollection())
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], __('api/vtual.public.directory_success'));
    }

    public function contents(Request $request): JsonResponse
    {
        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));

        $paginator = $this->profiles->paginatePublicContents($perPage);

        return $this->successResponse([
            'items' => $this->resolveResource(
                PublicContentResource::collection($paginator->getCollection())
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], __('api/vtual.public.contents_success'));
    }

    public function show(Request $request, string $creator): JsonResponse
    {
        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));
        $loaded = $this->profiles->showForPublic($creator, $perPage);

        return $this->successResponse(
            $this->resolveResource(new PublicVtuberProfileResource($loaded)),
            __('api/vtual.public.show_success'),
        );
    }

    /**
     * One-shot payload for the /vtual SPA home: featured event (settings),
     * live-now contents, upcoming schedule, and platform stats.
     * Forever-cached primitive payload; invalidated on writes via
     * PublicLandingCache::flush().
     */
    public function landing(): JsonResponse
    {
        $payload = \App\Domain\Vtual\Support\PublicLandingCache::remember(fn (): array => [
            'event' => $this->profiles->landingEvent(),
            'live_now' => $this->resolveResource(
                PublicContentResource::collection($this->profiles->liveNow())
            ),
            'schedule' => $this->resolveResource(
                PublicContentResource::collection($this->profiles->upcomingSchedule())
            ),
            'events' => $this->resolveResource(
                PublicEventResource::collection($this->profiles->upcomingEvents())
            ),
            'stats' => $this->profiles->publicStats(),
        ]);

        return $this->successResponse($payload, __('api/vtual.public.landing_success'));
    }

    public function stats(): JsonResponse
    {
        return $this->successResponse(
            $this->profiles->publicStats(),
            __('api/vtual.public.stats_success'),
        );
    }

    public function live(): JsonResponse
    {
        return $this->successResponse([
            'items' => $this->resolveResource(
                PublicContentResource::collection($this->profiles->liveNow())
            ),
        ], __('api/vtual.public.live_success'));
    }

    public function schedule(): JsonResponse
    {
        return $this->successResponse([
            'items' => $this->resolveResource(
                PublicContentResource::collection($this->profiles->upcomingSchedule())
            ),
        ], __('api/vtual.public.schedule_success'));
    }

    public function events(): JsonResponse
    {
        return $this->successResponse([
            'items' => $this->resolveResource(
                PublicEventResource::collection($this->profiles->upcomingEvents())
            ),
        ], __('api/vtual.public.events_success'));
    }
}
