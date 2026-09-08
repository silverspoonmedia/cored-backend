<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\Archivd\Http\Resources\FolderResource;
use App\Domain\Archivd\Http\Resources\HostedFileResource;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Services\FolderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Folder::class);

        $result = $this->folders->browse(
            $request->user(),
            $request->query('folder_id'),
            $request->query('user_id'),
            $request->query('workspace_id'),
        );

        return $this->successResponse([
            'owner_id' => $result['owner_id'],
            'workspace_id' => $result['workspace_id'],
            'workspace' => $result['workspace'] !== null
                ? [
                    'id' => $result['workspace']->id,
                    'kind' => $result['workspace']->kind instanceof \BackedEnum
                        ? $result['workspace']->kind->value
                        : $result['workspace']->kind,
                    'name' => $result['workspace']->name,
                ]
                : null,
            'folder' => $result['folder'] !== null
                ? $this->resolveResource(new FolderResource($result['folder']))
                : null,
            'breadcrumb' => $result['breadcrumb'],
            'folders' => $this->resolveResource(FolderResource::collection($result['folders'])),
            'files' => $this->resolveResource(HostedFileResource::collection($result['files'])),
        ], __('api/archivd.browse_success'));
    }
}
