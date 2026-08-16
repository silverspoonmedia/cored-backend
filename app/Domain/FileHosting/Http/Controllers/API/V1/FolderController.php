<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Requests\BulkDestroyRequest;
use App\Domain\FileHosting\Http\Requests\BulkVisibilityRequest;
use App\Domain\FileHosting\Http\Requests\MoveFolderRequest;
use App\Domain\FileHosting\Http\Requests\RenameFolderRequest;
use App\Domain\FileHosting\Http\Requests\SetVisibilityRequest;
use App\Domain\FileHosting\Http\Requests\StoreFolderRequest;
use App\Domain\FileHosting\Http\Resources\FolderResource;
use App\Domain\FileHosting\Http\Resources\PublicFolderResource;
use App\Domain\FileHosting\Http\Resources\PublicHostedFileResource;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Services\FolderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $this->authorize('create', Folder::class);

        $folder = $this->folders->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('parent_id'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new FolderResource($folder)),
            __('api/file-hosting.folder_created'),
            201,
        );
    }

    public function rename(RenameFolderRequest $request, string $folder): JsonResponse
    {
        $updated = $this->folders->rename(
            $request->user(),
            $folder,
            $request->validated('name'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new FolderResource($updated)),
            __('api/file-hosting.folder_renamed'),
        );
    }

    public function setVisibility(SetVisibilityRequest $request, string $folder): JsonResponse
    {
        $updated = $this->folders->setVisibility(
            $request->user(),
            $folder,
            $request->validated('visibility'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new FolderResource($updated)),
            __('api/file-hosting.visibility_updated'),
        );
    }

    public function publicShow(string $folder): JsonResponse
    {
        $data = $this->folders->publicBrowse($folder);

        return $this->successResponse([
            'folder' => $this->resolveResource(new PublicFolderResource($data['folder'])),
            'breadcrumb' => $data['breadcrumb'],
            'folders' => $this->resolveResource(PublicFolderResource::collection($data['folders'])),
            'files' => $this->resolveResource(PublicHostedFileResource::collection($data['files'])),
        ], __('api/file-hosting.browse_success'));
    }

    public function move(MoveFolderRequest $request, string $folder): JsonResponse
    {
        $updated = $this->folders->move(
            $request->user(),
            $folder,
            $request->validated('parent_id'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new FolderResource($updated)),
            __('api/file-hosting.folder_moved'),
        );
    }

    public function destroy(Request $request, string $folder): JsonResponse
    {
        $this->folders->softDelete(
            $request->user(),
            $folder,
            $request->query('user_id'),
            $request->query('workspace_id'),
        );

        return $this->successResponse(null, __('api/file-hosting.folder_deleted'));
    }

    public function restore(Request $request, string $folder): JsonResponse
    {
        $restored = $this->folders->restore(
            $request->user(),
            $folder,
            $request->query('user_id') ?? $request->input('user_id'),
            $request->query('workspace_id') ?? $request->input('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new FolderResource($restored)),
            __('api/file-hosting.folder_restored'),
        );
    }

    public function forceDestroy(Request $request, string $folder): JsonResponse
    {
        $this->folders->forceDelete(
            $request->user(),
            $folder,
            $request->query('user_id') ?? $request->input('user_id'),
            $request->query('workspace_id') ?? $request->input('workspace_id'),
        );

        return $this->successResponse(null, __('api/file-hosting.folder_force_deleted'));
    }

    public function bulkSetVisibility(BulkVisibilityRequest $request): JsonResponse
    {
        $count = $this->folders->bulkSetVisibility(
            $request->user(),
            $request->validated('ids'),
            $request->validated('visibility'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            ['affected' => $count],
            __('api/file-hosting.bulk_visibility_updated'),
        );
    }

    public function bulkDestroy(BulkDestroyRequest $request): JsonResponse
    {
        $count = $this->folders->bulkSoftDelete(
            $request->user(),
            $request->validated('ids'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            ['affected' => $count],
            __('api/file-hosting.bulk_folders_deleted'),
        );
    }
}
