<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreChannelRequest;
use App\Domain\Vtual\Http\Resources\ChannelClaimResource;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\ChannelClaimService;
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
     * Start channel attach claim (does not insert active channel).
     */
    public function store(StoreChannelRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('update', $creator);

        $claim = $this->claims->start($request->user(), $creator->id, $request->validated());

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
