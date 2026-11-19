<?php

namespace App\Domain\ActivityLog\Http\Controllers\API\V1;

use App\Domain\ActivityLog\Http\Requests\IndexActivityLogRequest;
use App\Domain\ActivityLog\Http\Resources\ActivityLogResource;
use App\Domain\ActivityLog\Services\ActivityLogService;
use App\Domain\ActivityLog\Support\ListActions\ActivityLogQueryRowActions;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Immutable audit trail entries from Spatie laravel-activitylog.
 *
 * Distinct from {@see NotificationController} (in-app bell).
 */
class ActivityLogController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly ActivityLogService $activityLogs,
        private readonly ActivityLogQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexActivityLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = $request->wantsQueryPaging()
            ? 500
            : (int) ($validated['limit'] ?? 50);
        $items = $this->activityLogs->list($validated, $limit);

        return $this->respondToQueryList(
            $request,
            $items,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(ActivityLogResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/activity-log.list_success'),
        );
    }

    public function show(Activity $activity): JsonResponse
    {
        $entry = $this->activityLogs->get($activity->id);

        return $this->successResponse(
            $this->resolveResource(new ActivityLogResource($entry)),
            __('api/activity-log.show_success'),
        );
    }

    public function forUser(User $user, IndexActivityLogRequest $request): JsonResponse
    {
        $limit = (int) ($request->validated()['limit'] ?? 20);
        $items = $this->activityLogs->listForSubject($user, $limit);

        return $this->successResponse(
            $this->resolveResource(ActivityLogResource::collection($items)),
            __('api/activity-log.subject_list_success'),
        );
    }
}
