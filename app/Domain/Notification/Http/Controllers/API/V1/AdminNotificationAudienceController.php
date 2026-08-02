<?php

namespace App\Domain\Notification\Http\Controllers\API\V1;

use App\Domain\Notification\Http\Requests\UpdateNotificationAudienceRequest;
use App\Domain\Notification\Services\NotificationAudienceService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationAudienceController extends Controller
{
    public function __construct(
        private readonly NotificationAudienceService $audiences,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        return ApiResponder::success(
            [
                'items' => $this->audiences->listPresented()->values()->all(),
                'assignable_roles' => $this->audiences->assignableRoles(),
            ],
            __('api/notifications.audiences.list_success'),
        );
    }

    public function show(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        return ApiResponder::success(
            $this->audiences->presentType($type),
            __('api/notifications.audiences.show_success'),
        );
    }

    public function update(UpdateNotificationAudienceRequest $request, string $type): JsonResponse
    {
        $updated = $this->audiences->update(
            $type,
            $request->notifySubjectUser(),
            $request->roleNames(),
            $request->userIds(),
        );

        return ApiResponder::success($updated, __('api/notifications.audiences.updated'));
    }
}
