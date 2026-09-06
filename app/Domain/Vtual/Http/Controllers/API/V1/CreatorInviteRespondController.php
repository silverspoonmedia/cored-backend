<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\ManagementCreatorInviteResource;
use App\Domain\Vtual\Services\ManagementCreatorInviteService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token-based creator-invite respond endpoints (kind=creator). Gated by
 * email-match inside the service (any authenticated user may hold a valid
 * token for their email).
 */
class CreatorInviteRespondController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementCreatorInviteService $creatorInvites,
    ) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $invite = $this->creatorInvites->showInviteByToken($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementCreatorInviteResource($invite)),
            __('api/vtual.management.invites_list_success'),
        );
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invite = $this->creatorInvites->acceptInvite($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementCreatorInviteResource($invite)),
            __('api/vtual.management.invite_accepted'),
        );
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invite = $this->creatorInvites->declineInvite($request->user(), $token);

        return $this->successResponse(
            $this->resolveResource(new ManagementCreatorInviteResource($invite)),
            __('api/vtual.management.invite_declined'),
        );
    }
}
