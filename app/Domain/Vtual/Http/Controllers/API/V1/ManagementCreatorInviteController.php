<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreCreatorInviteRequest;
use App\Domain\Vtual\Http\Resources\ManagementCreatorInviteResource;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementCreatorInviteService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementCreatorInviteController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementCreatorInviteService $creatorInvites,
    ) {}

    /**
     * vTuber profiles owned by the given email's account — candidate targets
     * for an `existing`-mode invite. Privacy-scoped to org managers.
     */
    public function candidates(Request $request, Management $management): JsonResponse
    {
        $profiles = $this->creatorInvites->candidates(
            $request->user(),
            $management->id,
            (string) $request->query('email', ''),
        );

        return $this->successResponse(
            $profiles->map(fn ($c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->display_name,
                'avatar_url' => $c->avatar_url,
            ])->all(),
            __('api/vtual.creator.list_success'),
        );
    }

    public function index(Request $request, Management $management): JsonResponse
    {
        $invites = $this->creatorInvites->listInvites($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(ManagementCreatorInviteResource::collection($invites)),
            __('api/vtual.management.invites_list_success'),
        );
    }

    public function store(StoreCreatorInviteRequest $request, Management $management): JsonResponse
    {
        $invite = $this->creatorInvites->inviteCreator(
            $request->user(),
            $management->id,
            $request->validated(),
        );

        return $this->successResponse(
            $this->resolveResource(new ManagementCreatorInviteResource($invite)),
            __('api/vtual.management.invite_sent'),
            201,
        );
    }

    public function destroy(Request $request, Management $management, string $invite): JsonResponse
    {
        $cancelled = $this->creatorInvites->cancelInvite($request->user(), $management->id, $invite);

        return $this->successResponse(
            $this->resolveResource(new ManagementCreatorInviteResource($cancelled)),
            __('api/vtual.management.invite_cancelled'),
        );
    }
}
