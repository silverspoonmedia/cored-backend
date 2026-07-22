<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreChannelRequest;
use App\Domain\Vtual\Http\Resources\ChannelClaimResource;
use App\Domain\Vtual\Http\Resources\ChannelResource;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\ChannelClaimService;
use App\Domain\Vtual\Services\ChannelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelClaimController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ChannelClaimService $claims,
        private readonly ChannelService $channels,
    ) {}

    public function index(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('view', $creator);

        return $this->successResponse(
            $this->resolveResource(ChannelClaimResource::collection(
                $this->claims->listForCreator($request->user(), $creator->id)
            )),
            __('api/vtual.channel_claim.list_success'),
        );
    }

    /**
     * Dual path:
     * - manage any (staff): resolve + active channel immediately (no claim row).
     * - owner/editor: start bio-token channel claim (pending only).
     */
    public function store(StoreChannelRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('update', $creator);

        $actor = $request->user();
        $data = $request->validated();

        if ($actor->can('manage any vtual resource')) {
            $channel = $this->channels->seedForStaff($actor, $creator->id, $data);

            return $this->successResponse(
                $this->resolveResource(new ChannelResource($channel)),
                __('api/vtual.channel.seeded'),
                201,
            );
        }

        $claim = $this->claims->start($actor, $creator->id, $data);

        return $this->successResponse(
            $this->resolveResource(new ChannelClaimResource($claim)),
            __('api/vtual.channel_claim.started'),
            201,
        );
    }

    public function show(Request $request, ChannelClaim $channelClaim): JsonResponse
    {
        $loaded = $this->claims->show($request->user(), $channelClaim->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelClaimResource($loaded)),
            __('api/vtual.channel_claim.show_success'),
        );
    }

    public function verify(Request $request, ChannelClaim $channelClaim): JsonResponse
    {
        $claim = $this->claims->verify($request->user(), $channelClaim->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelClaimResource($claim)),
            __('api/vtual.channel_claim.verified'),
        );
    }

    public function cancel(Request $request, ChannelClaim $channelClaim): JsonResponse
    {
        $claim = $this->claims->cancel($request->user(), $channelClaim->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelClaimResource($claim)),
            __('api/vtual.channel_claim.cancelled'),
        );
    }

    public function acceptAnyway(Request $request, ChannelClaim $channelClaim): JsonResponse
    {
        $claim = $this->claims->acceptAnyway($request->user(), $channelClaim->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelClaimResource($claim)),
            __('api/vtual.channel_claim.accepted_anyway'),
        );
    }
}
