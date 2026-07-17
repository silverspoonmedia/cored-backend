<?php

namespace App\Domain\ErrorLog\Http\Controllers\API\V1;

use App\Domain\ErrorLog\Http\Requests\IndexErrorLogRequest;
use App\Domain\ErrorLog\Http\Requests\UpdateErrorLogStatusRequest;
use App\Domain\ErrorLog\Http\Resources\ErrorLogResource;
use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Services\ErrorLogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Grouped persistent application errors for administrators and agents.
 *
 * Distinct from {@see \App\Domain\ActivityLog\Http\Controllers\API\V1\ActivityLogController}
 * (audit trail) and notification bell.
 */
class ErrorLogController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ErrorLogService $errorLogs,
    ) {}

    public function index(IndexErrorLogRequest $request): JsonResponse
    {
        $limit = (int) ($request->validated()['limit'] ?? 50);
        $items = $this->errorLogs->list($request->validated(), $limit);

        return $this->successResponse(
            $this->resolveResource(ErrorLogResource::collection($items)),
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
