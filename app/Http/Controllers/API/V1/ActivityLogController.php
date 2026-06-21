<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLog\IndexActivityLogRequest;
use App\Http\Resources\ActivityLog\ActivityLogResource;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Models\ApprovalLog;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Immutable audit trail entries from Spatie laravel-activitylog.
 *
 * Distinct from {@see NotificationController}
 * (in-app bell) and approval-domain {@see ApprovalLog}.
 */
class ActivityLogController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(IndexActivityLogRequest $request): JsonResponse
    {
        $limit = (int) ($request->validated()['limit'] ?? 50);
        $items = $this->activityLogs->list($request->validated(), $limit);

        return $this->successResponse(
            $this->resolveResource(ActivityLogResource::collection($items)),
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
