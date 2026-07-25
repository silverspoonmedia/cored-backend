<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Http\Requests\CompleteFileUploadRequest;
use App\Domain\FileHosting\Http\Requests\InitFileUploadRequest;
use App\Domain\FileHosting\Http\Requests\RecordFileUploadPartsRequest;
use App\Domain\FileHosting\Http\Requests\SignFileUploadPartsRequest;
use App\Domain\FileHosting\Http\Resources\HostedFileResource;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\Storage\SerializesDirectUploadSession;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use SerializesDirectUploadSession;

    public function __construct(
        private readonly DirectUploadService $uploads,
    ) {}

    public function store(InitFileUploadRequest $request): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $result = $this->uploads->initiate(
            $request->user(),
            $request->validated('name'),
            $request->validated('mime'),
            (int) $request->validated('size'),
            $request->validated('folder_id'),
            $request->validated('user_id'),
            $request->validated('disk'),
            $request->validated('workspace_id'),
            $request->validated('content_hash'),
        );

        return $this->successResponse(
            $this->serializeUploadSession($result, includeRecorded: true, extra: [
                'folder_id' => $result['upload']->folder_id,
                'resumed' => $result['resumed'] ?? false,
            ]),
            ($result['resumed'] ?? false)
                ? __('api/file-hosting.upload.resumed')
                : __('api/file-hosting.upload.initiated'),
            201,
        );
    }

    public function show(Request $request, string $upload): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $result = $this->uploads->status($request->user(), $upload);

        return $this->successResponse(
            $this->serializeUploadSession($result, includeRecorded: true, extra: [
                'folder_id' => $result['upload']->folder_id,
            ]),
            __('api/file-hosting.upload.status'),
        );
    }

    public function signParts(SignFileUploadPartsRequest $request, string $upload): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $parts = $this->uploads->signParts(
            $request->user(),
            $upload,
            array_map('intval', $request->validated('part_numbers')),
        );

        return $this->successResponse(
            ['parts' => $parts],
            __('api/file-hosting.upload.parts_signed'),
        );
    }

    public function recordParts(RecordFileUploadPartsRequest $request, string $upload): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $result = $this->uploads->recordParts(
            $request->user(),
            $upload,
            $request->validated('parts'),
        );

        return $this->successResponse(
            ['recorded_parts' => $result['recorded_parts']],
            __('api/file-hosting.upload.parts_recorded'),
        );
    }

    public function complete(CompleteFileUploadRequest $request, string $upload): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $result = $this->uploads->complete(
            $request->user(),
            $upload,
            $request->validated('parts'),
        );

        return $this->successResponse([
            'upload_id' => $result['upload']->id,
            'file' => $this->resolveResource(new HostedFileResource($result['file'])),
        ], __('api/file-hosting.upload.completed'));
    }

    public function destroy(Request $request, string $upload): JsonResponse
    {
        $this->authorize('create', HostedFile::class);

        $this->uploads->abort($request->user(), $upload);

        return $this->successResponse(null, __('api/file-hosting.upload.aborted'));
    }
}
