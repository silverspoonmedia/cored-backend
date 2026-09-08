<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\Archivd\Http\Requests\BulkDestroyRequest;
use App\Domain\Archivd\Http\Requests\BulkVisibilityRequest;
use App\Domain\Archivd\Http\Requests\MoveFileRequest;
use App\Domain\Archivd\Http\Requests\RenameFileRequest;
use App\Domain\Archivd\Http\Requests\SearchRequest;
use App\Domain\Archivd\Http\Requests\SetVisibilityRequest;
use App\Domain\Archivd\Http\Resources\HostedFileResource;
use App\Domain\Archivd\Http\Resources\PublicHostedFileResource;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\FileService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly FileService $files,
    ) {}

    public function show(Request $request, string $file): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $model = $this->files->show(
            $request->user(),
            $file,
            $request->query('user_id'),
            $request->query('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($model)),
            __('api/archivd.file_show_success'),
        );
    }

    public function rename(RenameFileRequest $request, string $file): JsonResponse
    {
        $updated = $this->files->rename(
            $request->user(),
            $file,
            $request->validated('name'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($updated)),
            __('api/archivd.file_renamed'),
        );
    }

    public function setVisibility(SetVisibilityRequest $request, string $file): JsonResponse
    {
        $updated = $this->files->setVisibility(
            $request->user(),
            $file,
            $request->validated('visibility'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($updated)),
            __('api/archivd.visibility_updated'),
        );
    }

    public function publicShow(string $file): JsonResponse
    {
        $model = $this->files->publicShow($file);

        return $this->successResponse(
            $this->resolveResource(new PublicHostedFileResource($model)),
            __('api/archivd.file_show_success'),
        );
    }

    public function publicDownload(string $file): JsonResponse
    {
        $payload = $this->files->publicDownload($file, request()->boolean('preview'));

        return $this->successResponse(
            $payload,
            __('api/archivd.download_ready'),
        );
    }

    public function move(MoveFileRequest $request, string $file): JsonResponse
    {
        $updated = $this->files->move(
            $request->user(),
            $file,
            $request->validated('folder_id'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($updated)),
            __('api/archivd.file_moved'),
        );
    }

    public function destroy(Request $request, string $file): JsonResponse
    {
        $this->files->softDelete(
            $request->user(),
            $file,
            $request->query('user_id'),
            $request->query('workspace_id'),
        );

        return $this->successResponse(null, __('api/archivd.file_deleted'));
    }

    public function restore(Request $request, string $file): JsonResponse
    {
        $restored = $this->files->restore(
            $request->user(),
            $file,
            $request->query('user_id') ?? $request->input('user_id'),
            $request->query('workspace_id') ?? $request->input('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($restored)),
            __('api/archivd.file_restored'),
        );
    }

    public function forceDestroy(Request $request, string $file): JsonResponse
    {
        $this->files->forceDelete(
            $request->user(),
            $file,
            $request->query('user_id') ?? $request->input('user_id'),
            $request->query('workspace_id') ?? $request->input('workspace_id'),
        );

        return $this->successResponse(null, __('api/archivd.file_force_deleted'));
    }

    public function download(Request $request, string $file): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $payload = $this->files->download(
            $request->user(),
            $file,
            $request->query('user_id'),
            $request->query('workspace_id'),
            $request->boolean('preview'),
        );

        return $this->successResponse(
            $payload,
            __('api/archivd.download_ready'),
        );
    }

    public function search(SearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $results = $this->files->search(
            $request->user(),
            $request->validated('q'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            $this->resolveResource(HostedFileResource::collection($results)),
            __('api/archivd.search_success'),
        );
    }

    public function bulkSetVisibility(BulkVisibilityRequest $request): JsonResponse
    {
        $count = $this->files->bulkSetVisibility(
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
        $count = $this->files->bulkSoftDelete(
            $request->user(),
            $request->validated('ids'),
            $request->validated('user_id'),
            $request->validated('workspace_id'),
        );

        return $this->successResponse(
            ['affected' => $count],
            __('api/archivd.bulk_files_deleted'),
        );
    }
}
