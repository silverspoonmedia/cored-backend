<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Http\Requests\StoreMicrostockWorkspaceRequest;
use App\Domain\Microstock\Services\MicrostockService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MicrostockService $microstock,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $workspace = $this->microstock->workspaceFor($request->user());

        return $this->successResponse([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'kind' => $workspace->kind->value,
            'status' => $workspace->status->value,
            'owner_user_id' => $workspace->owner_user_id,
        ], __('api/microstock.workspace_loaded'));
    }

    public function store(StoreMicrostockWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->microstock->createWorkspace(
            $request->user(),
            $request->validated('name'),
        );

        return $this->successResponse([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'kind' => $workspace->kind->value,
            'status' => $workspace->status->value,
            'owner_user_id' => $workspace->owner_user_id,
        ], __('api/microstock.workspace_created'), 201);
    }

    public function slots(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->microstock->slotStatus($request->user()),
            __('api/microstock.slots_loaded'),
        );
    }
}
