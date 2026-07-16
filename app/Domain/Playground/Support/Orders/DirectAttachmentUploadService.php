<?php

namespace App\Domain\Playground\Support\Orders;

use App\Domain\Playground\Exceptions\AttachmentUploadException;
use App\Domain\Playground\Models\AttachmentUpload;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Orchestrates browser-direct uploads to an S3-compatible disk via presigned URLs.
 *
 * Storage mechanics live in {@see S3DirectUploadEngine}; session state lives in
 * {@see AttachmentUpload}.
 */
class DirectAttachmentUploadService
{
    public function __construct(
        private readonly OrderAttachmentStorage $attachmentStorage,
        private readonly S3DirectUploadEngine $engine = new S3DirectUploadEngine,
    ) {}

    /**
     * @return array{
     *     upload: AttachmentUpload,
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     parts: list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     * }
     */
    public function initiate(string $name, string $mime, int $size): array
    {
        $this->assertUploadAllowed($name, $mime, $size);

        $diskName = (string) config('attachments.disk');
        $uploadId = (string) Str::uuid();
        $path = $this->buildPath($uploadId, $name);
        $expiresAt = Carbon::now()->addHours((int) config('attachments.session_ttl_hours'));

        $threshold = (int) config('attachments.multipart_threshold_bytes');
        $chunkSize = (int) config('attachments.chunk_size_bytes');
        $strategy = $this->engine->resolveStrategy($size, $threshold);
        $partCount = $this->engine->expectedPartCount($strategy, $size, $chunkSize);

        $s3UploadId = null;

        if ($strategy === S3DirectUploadEngine::STRATEGY_MULTIPART) {
            try {
                $s3UploadId = $this->engine->createMultipartUpload($diskName, $path, $mime);
            } catch (S3DirectUploadException $e) {
                throw $this->mapEngineException($e);
            }
        }

        $upload = AttachmentUpload::query()->create([
            'disk' => $diskName,
            'path' => $path,
            's3_upload_id' => $s3UploadId,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            'status' => AttachmentUpload::STATUS_PENDING,
            'parts' => [],
            'user_id' => auth()->id(),
            'expires_at' => $expiresAt,
        ]);

        $initialParts = $this->presignParts(
            $upload,
            $this->engine->missingPartNumbers(
                $upload->recordedParts(),
                $partCount,
                (int) config('attachments.max_presigned_parts_per_response'),
            ),
        );

        return [
            'upload' => $upload,
            'strategy' => $strategy,
            'chunk_size' => $strategy === S3DirectUploadEngine::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'parts' => $initialParts,
        ];
    }

    /**
     * @return array{
     *     upload: AttachmentUpload,
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     recorded_parts: list<array{part_number: int, etag: string, size: int}>,
     *     parts: list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     * }
     */
    public function status(string $uploadUuid): array
    {
        $upload = $this->findUsableUpload($uploadUuid);
        $partCount = $this->expectedPartCount($upload);

        return [
            'upload' => $upload,
            'strategy' => $upload->strategy,
            'chunk_size' => $upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART
                ? (int) config('attachments.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'recorded_parts' => $upload->recordedParts(),
            'parts' => $this->presignParts(
                $upload,
                $this->engine->missingPartNumbers(
                    $upload->recordedParts(),
                    $partCount,
                    (int) config('attachments.max_presigned_parts_per_response'),
                ),
            ),
        ];
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     */
    public function signParts(string $uploadUuid, array $partNumbers): array
    {
        $upload = $this->findUsableUpload($uploadUuid);

        if ($upload->strategy !== S3DirectUploadEngine::STRATEGY_MULTIPART) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.multipart_only'));
        }

        $partCount = $this->expectedPartCount($upload);
        $valid = [];

        foreach ($partNumbers as $partNumber) {
            $partNumber = (int) $partNumber;

            if ($partNumber < 1 || $partNumber > $partCount) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.invalid_part_number'));
            }

            $valid[] = $partNumber;
        }

        return $this->presignParts($upload, array_values(array_unique($valid)));
    }

    /**
     * @param  list<array{part_number: int, etag: string, size?: int}>  $parts
     */
    public function recordPart(string $uploadUuid, int $partNumber, string $etag, ?int $size = null): AttachmentUpload
    {
        $upload = $this->findUsableUpload($uploadUuid);

        if ($upload->strategy !== S3DirectUploadEngine::STRATEGY_MULTIPART) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.multipart_only'));
        }

        $etag = trim($etag, '"');
        $parts = $upload->recordedParts();
        $parts = array_values(array_filter(
            $parts,
            fn (array $part): bool => (int) ($part['part_number'] ?? 0) !== $partNumber,
        ));
        $parts[] = [
            'part_number' => $partNumber,
            'etag' => $etag,
            'size' => $size ?? 0,
        ];

        usort($parts, fn (array $a, array $b): int => $a['part_number'] <=> $b['part_number']);

        $upload->forceFill(['parts' => $parts])->save();

        return $upload->refresh();
    }

    /**
     * @param  list<array{part_number: int, etag: string}>|null  $parts
     * @return array{upload: AttachmentUpload, metadata: array{disk: string, path: string, name: string, size: int, mime: string, user_id: string|null}}
     */
    public function complete(string $uploadUuid, ?array $parts = null): array
    {
        $upload = $this->findUsableUpload($uploadUuid);
        $merged = $upload->parts;

        if ($upload->strategy === S3DirectUploadEngine::STRATEGY_SINGLE) {
            if (! $this->attachmentStorage->pathExists($upload->path, $upload->disk)) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.object_missing'));
            }
        } else {
            $merged = $this->engine->mergeParts($upload->recordedParts(), $parts);

            try {
                $this->engine->assertAllPartsPresent($merged, $this->expectedPartCount($upload));
                $this->engine->completeMultipartUpload(
                    $upload->disk,
                    $upload->path,
                    (string) $upload->s3_upload_id,
                    $merged,
                );
            } catch (S3DirectUploadException $e) {
                throw $this->mapEngineException($e);
            }
        }

        $upload->forceFill([
            'status' => AttachmentUpload::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'parts' => $upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART
                ? $merged
                : $upload->parts,
        ])->save();

        return [
            'upload' => $upload->refresh(),
            'metadata' => $this->toMetadata($upload),
        ];
    }

    public function abort(string $uploadUuid): void
    {
        $upload = AttachmentUpload::query()->find($uploadUuid);

        if ($upload === null) {
            return;
        }

        if ($upload->status !== AttachmentUpload::STATUS_PENDING) {
            return;
        }

        if ($upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART && $upload->s3_upload_id) {
            try {
                $this->engine->abortMultipartUpload(
                    $upload->disk,
                    $upload->path,
                    (string) $upload->s3_upload_id,
                );
            } catch (S3DirectUploadException $e) {
                throw $this->mapEngineException($e);
            }
        } elseif ($this->attachmentStorage->pathExists($upload->path, $upload->disk)) {
            $this->attachmentStorage->deleteMany([['path' => $upload->path, 'disk' => $upload->disk]]);
        }

        $upload->forceFill(['status' => AttachmentUpload::STATUS_ABORTED])->save();
    }

    /**
     * @param  list<string>  $uploadUuids
     * @return list<array{disk: string, path: string, name: string, size: int, mime: string, user_id: string|null}>
     */
    public function resolveStaged(array $uploadUuids): array
    {
        if ($uploadUuids === []) {
            return [];
        }

        $uploads = AttachmentUpload::query()
            ->whereIn('id', $uploadUuids)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($uploadUuids as $uuid) {
            $upload = $uploads->get($uuid);

            if ($upload === null) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.not_found'));
            }

            if (! $upload->isCompleted()) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.not_completed'));
            }

            if ($upload->isExpired()) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.expired'));
            }

            if (! $this->isPathAllowed($upload->path)) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.invalid_path'));
            }

            if (! $this->attachmentStorage->pathExists($upload->path, $upload->disk)) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.object_missing'));
            }

            $resolved[] = $this->toMetadata($upload);
        }

        return $resolved;
    }

    protected function assertUploadAllowed(string $name, string $mime, int $size): void
    {
        try {
            $this->engine->assertUploadAllowed(
                $name,
                $mime,
                $size,
                (int) config('attachments.max_file_size_bytes'),
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
        }
    }

    private function buildPath(string $uploadId, string $name): string
    {
        $directory = trim((string) config('attachments.directory'), '/');
        $safeName = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return $directory.'/'.$uploadId.'/'.$safeName.($extension !== '' ? '.'.$extension : '');
    }

    private function isPathAllowed(string $path): bool
    {
        $directory = trim((string) config('attachments.directory'), '/');

        return str_starts_with($path, $directory.'/');
    }

    private function findUsableUpload(string $uploadUuid): AttachmentUpload
    {
        $upload = AttachmentUpload::query()->find($uploadUuid);

        if ($upload === null) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.not_found'));
        }

        if ($upload->status !== AttachmentUpload::STATUS_PENDING) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.not_pending'));
        }

        if ($upload->isExpired()) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.expired'));
        }

        return $upload;
    }

    private function expectedPartCount(AttachmentUpload $upload): int
    {
        return $this->engine->expectedPartCount(
            $upload->strategy,
            $upload->size,
            (int) config('attachments.chunk_size_bytes'),
        );
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     */
    private function presignParts(AttachmentUpload $upload, array $partNumbers): array
    {
        try {
            return $this->engine->presignParts(
                $upload->disk,
                $upload->path,
                $upload->strategy,
                $upload->mime,
                $upload->s3_upload_id,
                $partNumbers,
                (int) config('attachments.presign_ttl_minutes'),
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
        }
    }

    /**
     * @return array{disk: string, path: string, name: string, size: int, mime: string, user_id: string|null}
     */
    private function toMetadata(AttachmentUpload $upload): array
    {
        return [
            'disk' => $upload->disk,
            'path' => $upload->path,
            'name' => $upload->original_name,
            'size' => $upload->size,
            'mime' => $upload->mime,
            'user_id' => $upload->user_id,
        ];
    }

    private function mapEngineException(S3DirectUploadException $e): AttachmentUploadException
    {
        $message = match ($e->reason) {
            S3DirectUploadException::UNSUPPORTED_DISK => __('api/playground-attachment-uploads.unsupported_disk'),
            S3DirectUploadException::INCOMPLETE_PARTS => __('api/playground-attachment-uploads.incomplete_parts'),
            S3DirectUploadException::INVALID_SIZE => __('api/playground-attachment-uploads.invalid_size'),
            S3DirectUploadException::FILE_TOO_LARGE => __('api/playground-attachment-uploads.file_too_large'),
            S3DirectUploadException::INVALID_NAME => __('api/playground-attachment-uploads.invalid_name'),
            default => $e->getMessage(),
        };

        return new AttachmentUploadException($message);
    }
}
