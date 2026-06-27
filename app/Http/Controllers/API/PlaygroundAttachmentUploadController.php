<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\CompleteAttachmentUploadRequest;
use App\Http\Requests\Playground\InitAttachmentUploadRequest;
use App\Http\Requests\Playground\SignAttachmentUploadPartsRequest;
use App\Support\Orders\DirectAttachmentUploadService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Browser-direct attachment uploads to S3-compatible storage via presigned URLs.
 */
class PlaygroundAttachmentUploadController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DirectAttachmentUploadService $uploads,
    ) {}

    public function store(InitAttachmentUploadRequest $request): JsonResponse
    {
        $result = $this->uploads->initiate(
            $request->validated('name'),
            $request->validated('mime'),
            (int) $request->validated('size'),
        );

        return $this->successResponse(
            $this->serializeSession($result),
            __('api/playground-attachment-uploads.initiated'),
            201,
        );
    }

    public function show(string $upload): JsonResponse
    {
        $result = $this->uploads->status($upload);

        return $this->successResponse(
            $this->serializeSession($result, includeRecorded: true),
            __('api/playground-attachment-uploads.status'),
        );
    }

    public function signParts(SignAttachmentUploadPartsRequest $request, string $upload): JsonResponse
    {
        $parts = $this->uploads->signParts(
            $upload,
            array_map('intval', $request->validated('part_numbers')),
        );

        return $this->successResponse(
            ['parts' => $parts],
            __('api/playground-attachment-uploads.parts_signed'),
        );
    }

    public function complete(CompleteAttachmentUploadRequest $request, string $upload): JsonResponse
    {
        $result = $this->uploads->complete(
            $upload,
            $request->validated('parts'),
        );

        return $this->successResponse(
            [
                'upload_id' => $result['upload']->id,
                'metadata' => $result['metadata'],
            ],
            __('api/playground-attachment-uploads.completed'),
        );
    }

    public function destroy(string $upload): JsonResponse
    {
        $this->uploads->abort($upload);

        return $this->successResponse(null, __('api/playground-attachment-uploads.aborted'));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function serializeSession(array $result, bool $includeRecorded = false): array
    {
        $upload = $result['upload'];

        $payload = [
            'upload_id' => $upload->id,
            'strategy' => $result['strategy'],
            'path' => $upload->path,
            'chunk_size' => $result['chunk_size'],
            'part_count' => $result['part_count'],
            'parts' => $result['parts'],
            'expires_at' => $upload->expires_at?->toIso8601String(),
        ];

        if ($includeRecorded) {
            $payload['recorded_parts'] = $result['recorded_parts'] ?? $upload->recordedParts();
        }

        return $payload;
    }
}
