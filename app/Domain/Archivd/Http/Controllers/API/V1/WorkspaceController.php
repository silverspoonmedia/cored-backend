<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\ActivityLog\Support\SensitiveActivityPropertyPresenter;
use App\Domain\Archivd\Http\Requests\AcceptWorkspaceInviteRequest;
use App\Domain\Archivd\Http\Requests\InviteWorkspaceMemberRequest;
use App\Domain\Archivd\Http\Requests\StoreWorkspaceRequest;
use App\Domain\Archivd\Http\Requests\UpdateWorkspaceMemberRoleRequest;
use App\Domain\Archivd\Http\Resources\WorkspaceInviteResource;
use App\Domain\Archivd\Http\Resources\WorkspaceMemberResource;
use App\Domain\Archivd\Http\Resources\WorkspaceResource;
use App\Domain\Archivd\Services\WorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class WorkspaceController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $list = $this->workspaces->listForUser($request->user());

        return $this->successResponse(
            $this->resolveResource(WorkspaceResource::collection($list)),
            __('api/archivd.workspace.list_success'),
        );
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaces->create($request->user(), $request->validated('name'));

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($workspace)),
            __('api/archivd.workspace.created'),
            201,
        );
    }

    public function show(Request $request, string $workspace): JsonResponse
    {
        $model = $this->workspaces->show($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($model)),
            __('api/archivd.workspace.show_success'),
        );
    }

    public function archive(Request $request, string $workspace): JsonResponse
    {
        $model = $this->workspaces->archive($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($model)),
            __('api/archivd.workspace.archived_success'),
        );
    }

    public function members(Request $request, string $workspace): JsonResponse
    {
        $members = $this->workspaces->listMembers($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(WorkspaceMemberResource::collection($members)),
            __('api/archivd.workspace.members_success'),
        );
    }

    public function invite(InviteWorkspaceMemberRequest $request, string $workspace): JsonResponse
    {
        $data = $request->validated();

        $invite = $this->workspaces->invite(
            $request->user(),
            $workspace,
            $data['email'],
            $data['role'],
        );

        return $this->successResponse(
            $this->resolveResource(new WorkspaceInviteResource($invite, includeToken: true)),
            __('api/archivd.workspace.invite_sent'),
            201,
        );
    }

    public function acceptInvite(AcceptWorkspaceInviteRequest $request): JsonResponse
    {
        $member = $this->workspaces->acceptInvite($request->user(), $request->validated('token'));

        return $this->successResponse(
            $this->resolveResource(new WorkspaceMemberResource($member)),
            __('api/archivd.workspace.invite_accepted'),
        );
    }

    public function updateMemberRole(UpdateWorkspaceMemberRoleRequest $request, string $workspace, string $user): JsonResponse
    {
        $member = $this->workspaces->updateMemberRole(
            $request->user(),
            $workspace,
            $user,
            $request->validated('role'),
        );

        return $this->successResponse(
            $this->resolveResource(new WorkspaceMemberResource($member)),
            __('api/archivd.workspace.role_updated'),
        );
    }

    public function removeMember(Request $request, string $workspace, string $user): JsonResponse
    {
        $this->workspaces->removeMember($request->user(), $workspace, $user);

        return $this->successResponse(null, __('api/archivd.workspace.member_removed'));
    }

    public function activity(Request $request, string $workspace): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows = $this->workspaces->activity($request->user(), $workspace, $limit);

        $payload = $rows->map(static function (Activity $row): array {
            return [
                'id' => $row->id,
                'event' => $row->event ?? $row->description,
                'description' => $row->description,
                'causer' => $row->causer !== null ? [
                    'id' => (string) $row->causer->getKey(),
                    'name' => $row->causer->name ?? null,
                    'email' => $row->causer->email ?? null,
                ] : null,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'properties' => SensitiveActivityPropertyPresenter::forApi(
                    $row->properties?->toArray() ?? [],
                    $row->id !== null ? (string) $row->id : null,
                ),
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->successResponse(
            $payload,
            __('api/archivd.workspace.activity_success'),
        );
    }
}
