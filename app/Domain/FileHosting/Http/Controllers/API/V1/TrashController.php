<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Resources\FolderResource;
use App\Domain\FileHosting\Http\Resources\HostedFileResource;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\FolderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly FolderService $folders,
        private readonly FileService $files,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Folder::class);

        $userId = $request->query('user_id');
        $workspaceId = $request->query('workspace_id');

        return $this->successResponse([
            'folders' => $this->resolveResource(
                FolderResource::collection($this->folders->trash($request->user(), $userId, $workspaceId)),
            ),
            'files' => $this->resolveResource(
                HostedFileResource::collection($this->files->trash($request->user(), $userId, $workspaceId)),
            ),
        ], __('api/file-hosting.trash_success'));
    }
}
