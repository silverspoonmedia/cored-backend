<?php

namespace App\Domain\ErrorLog\Http\Controllers\API\V1;

use App\Domain\ErrorLog\Http\Requests\IndexErrorLogRequest;
use App\Domain\ErrorLog\Http\Requests\UpdateErrorLogStatusRequest;
use App\Domain\ErrorLog\Http\Resources\ErrorLogResource;
use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Services\ErrorLogService;
use App\Domain\ErrorLog\Support\ListActions\ErrorLogQueryRowActions;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Grouped persistent application errors for administrators and agents.
 *
 * Distinct from the activity trail controller in the ActivityLog domain
 * and the notification bell.
 */
class ErrorLogController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly ErrorLogService $errorLogs,
        private readonly ErrorLogQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexErrorLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = $request->wantsQueryPaging()
            ? 500
            : (int) ($validated['limit'] ?? 50);
        $items = $this->errorLogs->list($validated, $limit);

        return $this->respondToQueryList(
            $request,
            $items,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(ErrorLogResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/error-log.list_success'),
        );
    }

    public function show(ErrorLog $errorLog): JsonResponse
    {
        $entry = $this->errorLogs->get($errorLog->id);

        return $this->successResponse(
            $this->resolveResource(new ErrorLogResource($entry)),
            __('api/error-log.show_success'),
        );
    }

    public function updateStatus(UpdateErrorLogStatusRequest $request, ErrorLog $errorLog): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        $userId = is_string($userId) ? $userId : null;

        $entry = $this->errorLogs->updateStatus(
            $errorLog,
            $request->validated('status'),
            $userId,
        );

        return $this->successResponse(
            $this->resolveResource(new ErrorLogResource($entry)),
            __('api/error-log.status_updated'),
        );
    }
}
