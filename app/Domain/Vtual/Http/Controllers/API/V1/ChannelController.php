<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\ChannelResource;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\ChannelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ChannelService $channels,
    ) {}

    public function index(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('viewAny', Channel::class);
        $this->authorize('view', $creator);

        return $this->successResponse(
            $this->resolveResource(ChannelResource::collection(
                $this->channels->listForCreator($request->user(), $creator->id)
            )),
            __('api/vtual.channel.list_success'),
        );
    }

    public function archivedIndex(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('viewAny', Channel::class);
        $this->authorize('view', $creator);

        return $this->successResponse(
            $this->resolveResource(ChannelResource::collection(
                $this->channels->listArchivedForCreator($request->user(), $creator->id)
            )),
            __('api/vtual.channel.archived_list_success'),
        );
    }

    public function show(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $loaded = $this->channels->show($request->user(), $channel->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($loaded)),
            __('api/vtual.channel.show_success'),
        );
    }

    public function refresh(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('view', $channel);

        $queued = $this->channels->refresh($request->user(), $channel->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($queued)),
            __('api/vtual.channel.refresh_queued'),
        );
    }

    public function archive(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('view', $channel);

        $archived = $this->channels->archive($request->user(), $channel->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($archived)),
            __('api/vtual.channel.archived'),
        );
    }

    public function destroy(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('delete', $channel);

        $this->channels->deletePending($request->user(), $channel->id);

        return $this->successResponse(null, __('api/vtual.channel.deleted'));
    }

    public function restore(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('view', $channel);

        $restored = $this->channels->restore($request->user(), $channel->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($restored)),
            __('api/vtual.channel.restored'),
        );
    }

    public function promote(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('view', $channel);

        $promoted = $this->channels->promoteDiscoveredChannel($request->user(), $channel->id);

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($promoted)),
            __('api/vtual.channel.promoted'),
        );
    }
}
