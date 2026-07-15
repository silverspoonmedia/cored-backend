<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Requests\MoveFileRequest;
use App\Domain\FileHosting\Http\Requests\RenameFileRequest;
use App\Domain\FileHosting\Http\Requests\SearchRequest;
use App\Domain\FileHosting\Http\Resources\HostedFileResource;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\FileService;
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
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($model)),
            __('api/file-hosting.file_show_success'),
        );
    }

    public function rename(RenameFileRequest $request, string $file): JsonResponse
    {
        $updated = $this->files->rename(
            $request->user(),
            $file,
            $request->validated('name'),
            $request->validated('user_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($updated)),
            __('api/file-hosting.file_renamed'),
        );
    }

    public function move(MoveFileRequest $request, string $file): JsonResponse
    {
        $updated = $this->files->move(
            $request->user(),
            $file,
            $request->validated('folder_id'),
            $request->validated('user_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($updated)),
            __('api/file-hosting.file_moved'),
        );
    }

    public function destroy(Request $request, string $file): JsonResponse
    {
        $this->files->softDelete(
            $request->user(),
            $file,
            $request->query('user_id'),
        );

        return $this->successResponse(null, __('api/file-hosting.file_deleted'));
    }

    public function restore(Request $request, string $file): JsonResponse
    {
        $restored = $this->files->restore(
            $request->user(),
            $file,
            $request->query('user_id') ?? $request->input('user_id'),
        );

        return $this->successResponse(
            $this->resolveResource(new HostedFileResource($restored)),
            __('api/file-hosting.file_restored'),
        );
    }

    public function forceDestroy(Request $request, string $file): JsonResponse
    {
        $this->files->forceDelete(
            $request->user(),
            $file,
            $request->query('user_id') ?? $request->input('user_id'),
        );

        return $this->successResponse(null, __('api/file-hosting.file_force_deleted'));
    }

    public function download(Request $request, string $file): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $url = $this->files->downloadUrl(
            $request->user(),
            $file,
            $request->query('user_id'),
        );

        return $this->successResponse(
            ['url' => $url],
            __('api/file-hosting.download_ready'),
        );
    }

    public function search(SearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $results = $this->files->search(
            $request->user(),
            $request->validated('q'),
            $request->validated('user_id'),
        );

        return $this->successResponse(
            $this->resolveResource(HostedFileResource::collection($results)),
            __('api/file-hosting.search_success'),
        );
    }
}
