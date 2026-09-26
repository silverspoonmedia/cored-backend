<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreCreatorClaimRequest;
use App\Domain\Vtual\Http\Resources\CreatorClaimResource;
use App\Domain\Vtual\Http\Resources\CreatorResource;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\VtualClaimQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorClaimController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CreatorClaimService $claims,
        private readonly VtualClaimQueryRowActions $queryRowActions,
    ) {}

    public function claimable(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(CreatorResource::collection($this->claims->listClaimableCreators())),
            __('api/vtual.claim.claimable_success'),
        );
    }

    public function mine(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $this->claims->listMine($request->user(), $perPage, $page);

        return $this->successResponse([
            'items' => QueryRowActionAttacher::attach(
                $this->resolveResource(
                    CreatorClaimResource::collection($list['items'])
                ),
                $list['items'],
                $this->queryRowActions,
                $request->user(),
            ),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $list['total'],
                'has_more' => $page * $perPage < $list['total'],
            ],
        ], __('api/vtual.claim.list_success'));
    }

    public function pending(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $this->claims->listPendingForStaff($request->user(), $perPage, $page);

        return $this->successResponse([
            'items' => QueryRowActionAttacher::attach(
                $this->resolveResource(
                    CreatorClaimResource::collection($list['items'])
                ),
                $list['items'],
                $this->queryRowActions,
                $request->user(),
            ),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $list['total'],
                'has_more' => $page * $perPage < $list['total'],
            ],
        ], __('api/vtual.claim.pending_success'));
    }

    public function store(StoreCreatorClaimRequest $request): JsonResponse
    {
        $data = $request->validated();
        $claim = $this->claims->start(
            $request->user(),
            (string) $data['creator_id'],
            (string) $data['channel_id'],
        );

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($claim)),
            __('api/vtual.claim.started'),
            201,
        );
    }

    public function show(Request $request, CreatorClaim $claim): JsonResponse
    {
        $loaded = $this->claims->show($request->user(), $claim->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($loaded)),
            __('api/vtual.claim.show_success'),
        );
    }

    public function verify(Request $request, CreatorClaim $claim): JsonResponse
    {
        $queued = $this->claims->queueVerify($request->user(), $claim->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($queued)),
            __('api/vtual.claim.verify_queued'),
        );
    }

    public function verifyDiscord(Request $request, CreatorClaim $claim): JsonResponse
    {
        $verified = $this->claims->verifyViaDiscord($request->user(), $claim->id);

        $message = $verified->status === \App\Domain\Vtual\Enums\CreatorClaimStatus::Verified
            ? __('api/vtual.claim.verified')
            : __('api/vtual.claim.discord_no_match');

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($verified)),
            $message,
        );
    }

    public function cancel(Request $request, CreatorClaim $claim): JsonResponse
    {
        $cancelled = $this->claims->cancel($request->user(), $claim->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($cancelled)),
            __('api/vtual.claim.cancelled'),
        );
    }

    public function acceptAnyway(Request $request, CreatorClaim $claim): JsonResponse
    {
        $verified = $this->claims->acceptAnyway($request->user(), $claim->id);

        return $this->successResponse(
            $this->resolveResource(new CreatorClaimResource($verified)),
            __('api/vtual.claim.accepted_anyway'),
        );
    }
}
