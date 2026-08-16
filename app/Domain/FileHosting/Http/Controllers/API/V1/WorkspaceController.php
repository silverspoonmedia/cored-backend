<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Resources\WorkspaceInviteResource;
use App\Domain\FileHosting\Http\Resources\WorkspaceMemberResource;
use App\Domain\FileHosting\Http\Resources\WorkspaceResource;
use App\Domain\FileHosting\Services\WorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            __('api/file-hosting.workspace.list_success'),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspaces->create($request->user(), $data['name']);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($workspace)),
            __('api/file-hosting.workspace.created'),
            201,
        );
    }

    public function show(Request $request, string $workspace): JsonResponse
    {
        $model = $this->workspaces->show($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($model)),
            __('api/file-hosting.workspace.show_success'),
        );
    }

    public function archive(Request $request, string $workspace): JsonResponse
    {
        $model = $this->workspaces->archive($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceResource($model)),
            __('api/file-hosting.workspace.archived_success'),
        );
    }

    public function members(Request $request, string $workspace): JsonResponse
    {
        $members = $this->workspaces->listMembers($request->user(), $workspace);

        return $this->successResponse(
            $this->resolveResource(WorkspaceMemberResource::collection($members)),
            __('api/file-hosting.workspace.members_success'),
        );
    }

    public function invite(Request $request, string $workspace): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,editor,viewer'],
        ]);

        $invite = $this->workspaces->invite(
            $request->user(),
            $workspace,
            $data['email'],
            $data['role'],
        );

        return $this->successResponse(
            $this->resolveResource(new WorkspaceInviteResource($invite, includeToken: true)),
            __('api/file-hosting.workspace.invite_sent'),
            201,
        );
    }

    public function acceptInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $member = $this->workspaces->acceptInvite($request->user(), $data['token']);

        return $this->successResponse(
            $this->resolveResource(new WorkspaceMemberResource($member)),
            __('api/file-hosting.workspace.invite_accepted'),
        );
    }

    public function updateMemberRole(Request $request, string $workspace, string $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,editor,viewer'],
        ]);

        $member = $this->workspaces->updateMemberRole(
            $request->user(),
            $workspace,
            $user,
            $data['role'],
        );

        return $this->successResponse(
            $this->resolveResource(new WorkspaceMemberResource($member)),
            __('api/file-hosting.workspace.role_updated'),
        );
    }

    public function removeMember(Request $request, string $workspace, string $user): JsonResponse
    {
        $this->workspaces->removeMember($request->user(), $workspace, $user);

        return $this->successResponse(null, __('api/file-hosting.workspace.member_removed'));
    }

    public function activity(Request $request, string $workspace): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows = $this->workspaces->activity($request->user(), $workspace, $limit);

        $payload = $rows->map(static function ($row): array {
            return [
                'id' => $row->id,
                'event' => $row->event ?? $row->description,
                'description' => $row->description,
                'causer' => $row->causer !== null ? [
                    'id' => $row->causer->id,
                    'name' => $row->causer->name ?? null,
                    'email' => $row->causer->email ?? null,
                ] : null,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'properties' => $row->properties?->toArray() ?? [],
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->successResponse(
            $payload,
            __('api/file-hosting.workspace.activity_success'),
        );
    }
}
