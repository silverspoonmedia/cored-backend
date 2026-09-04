<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\AssignCreatorRequest;
use App\Domain\Vtual\Http\Requests\StoreManagementInviteRequest;
use App\Domain\Vtual\Http\Requests\StoreManagementRequest;
use App\Domain\Vtual\Http\Requests\UpdateManagementMemberRequest;
use App\Domain\Vtual\Http\Requests\UpdateManagementRequest;
use App\Domain\Vtual\Http\Resources\ManagementInviteResource;
use App\Domain\Vtual\Http\Resources\ManagementMemberResource;
use App\Domain\Vtual\Http\Resources\ManagementResource;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\ManagementQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementService $managements,
        private readonly ManagementQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Management::class);

        $list = $this->managements->listForUser($request->user());

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(ManagementResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/vtual.management.list_success'),
        );
    }

    public function store(StoreManagementRequest $request): JsonResponse
    {
        $this->authorize('create', Management::class);

        $management = $this->managements->create($request->user(), $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($management)),
            __('api/vtual.management.created'),
            201,
        );
    }

    public function show(Request $request, Management $management): JsonResponse
    {
        $this->authorize('view', $management);

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($loaded)),
            __('api/vtual.management.show_success'),
        );
    }

    public function update(UpdateManagementRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $updated = $this->managements->update($request->user(), $management->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($updated)),
            __('api/vtual.management.updated'),
        );
    }

    public function assignCreator(AssignCreatorRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $assignment = $this->managements->assignCreator(
            $request->user(),
            $management->id,
            $request->validated('creator_id'),
        );

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            [
                'assignment_id' => $assignment->id,
                'management' => $this->resolveResource(new ManagementResource($loaded)),
            ],
            __('api/vtual.management.creator_assigned'),
        );
    }

    public function unassignCreator(Request $request, Management $management, string $creator): JsonResponse
    {
        $this->authorize('update', $management);

        $this->managements->unassignCreator($request->user(), $management->id, $creator);

        $loaded = $this->managements->show($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(new ManagementResource($loaded)),
            __('api/vtual.management.creator_unassigned'),
        );
    }

    public function members(Request $request, Management $management): JsonResponse
    {
        $this->authorize('view', $management);

        $members = $this->managements->listMembers($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(ManagementMemberResource::collection($members)),
            __('api/vtual.management.members_list_success'),
        );
    }

    public function addMember(StoreManagementInviteRequest $request, Management $management): JsonResponse
    {
        $this->authorize('update', $management);

        $invite = $this->managements->inviteMember($request->user(), $management->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementInviteResource($invite)),
            __('api/vtual.management.invite_sent'),
            201,
        );
    }

    public function invites(Request $request, Management $management): JsonResponse
    {
        $this->authorize('view', $management);

        $invites = $this->managements->listInvites($request->user(), $management->id);

        return $this->successResponse(
            $this->resolveResource(ManagementInviteResource::collection($invites)),
            __('api/vtual.management.invites_list_success'),
        );
    }

    public function cancelInvite(Request $request, Management $management, string $invite): JsonResponse
    {
        $this->authorize('update', $management);

        $invite = $this->managements->cancelInvite($request->user(), $management->id, $invite);

        $message = $invite->status instanceof \BackedEnum && $invite->status->value === 'revoked'
            ? __('api/vtual.management.invite_revoked')
            : __('api/vtual.management.invite_cancelled');

        return $this->successResponse(
            $this->resolveResource(new ManagementInviteResource($invite)),
            $message,
        );
    }

    public function updateMember(UpdateManagementMemberRequest $request, Management $management, string $member): JsonResponse
    {
        $this->authorize('update', $management);

        $updated = $this->managements->updateMemberRole($request->user(), $management->id, $member, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ManagementMemberResource($updated)),
            __('api/vtual.management.member_updated'),
        );
    }

    public function removeMember(Request $request, Management $management, string $member): JsonResponse
    {
        $this->authorize('update', $management);

        $this->managements->removeMember($request->user(), $management->id, $member);

        return $this->successResponse(
            null,
            __('api/vtual.management.member_removed'),
        );
    }
}
