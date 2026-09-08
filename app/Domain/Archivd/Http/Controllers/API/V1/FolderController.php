<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\Archivd\Http\Requests\BulkDestroyRequest;
use App\Domain\Archivd\Http\Requests\BulkVisibilityRequest;
use App\Domain\Archivd\Http\Requests\MoveFolderRequest;
use App\Domain\Archivd\Http\Requests\RenameFolderRequest;
use App\Domain\Archivd\Http\Requests\SetVisibilityRequest;
use App\Domain\Archivd\Http\Requests\StoreFolderRequest;
use App\Domain\Archivd\Http\Resources\FolderResource;
use App\Domain\Archivd\Http\Resources\PublicFolderResource;
use App\Domain\Archivd\Http\Resources\PublicHostedFileResource;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Services\FolderService;
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
            __('api/archivd.folder_created'),
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
            __('api/archivd.folder_renamed'),
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
            __('api/archivd.visibility_updated'),
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
        ], __('api/archivd.browse_success'));
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
            __('api/archivd.folder_moved'),
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

        return $this->successResponse(null, __('api/archivd.folder_deleted'));
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
            __('api/archivd.folder_restored'),
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

        return $this->successResponse(null, __('api/archivd.folder_force_deleted'));
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
            __('api/archivd.bulk_visibility_updated'),
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
            __('api/archivd.bulk_folders_deleted'),
        );
    }
}
