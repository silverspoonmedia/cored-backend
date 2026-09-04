<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\ManagementInviteResource;
use App\Domain\Vtual\Services\ManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token-based invite respond endpoints. Gated by email-match inside the
 * service (any authenticated user may hold a valid token for their email).
 */
class ManagementInviteRespondController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementService $managements,
    ) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $invite = $this->managements->showInviteByToken($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementInviteResource($invite)),
            __('api/vtual.management.invites_list_success'),
        );
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invite = $this->managements->acceptInvite($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementInviteResource($invite)),
            __('api/vtual.management.invite_accepted'),
        );
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invite = $this->managements->declineInvite($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementInviteResource($invite)),
            __('api/vtual.management.invite_declined'),
        );
    }
}
