<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreChannelRequest;
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

    public function store(StoreChannelRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('create', Channel::class);
        $this->authorize('update', $creator);

        $channel = $this->channels->create($request->user(), $creator->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ChannelResource($channel)),
            __('api/vtual.channel.created'),
            201,
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
}
