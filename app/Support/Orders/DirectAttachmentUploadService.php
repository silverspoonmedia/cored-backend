<?php

namespace App\Support\Orders;

use App\Exceptions\Playground\AttachmentUploadException;
use App\Models\AttachmentUpload;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates browser-direct uploads to an S3-compatible disk via presigned URLs.
 *
 * Small files receive a single presigned PUT; larger files use S3 multipart upload
 * with per-part presigned URLs. Session state lives in {@see AttachmentUpload}.
 */
class DirectAttachmentUploadService
{
    public function __construct(
        private readonly OrderAttachmentStorage $attachmentStorage,
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
        $strategy = $size < $threshold
            ? AttachmentUpload::STRATEGY_SINGLE
            : AttachmentUpload::STRATEGY_MULTIPART;

        $s3UploadId = null;
        $partCount = 1;

        if ($strategy === AttachmentUpload::STRATEGY_MULTIPART) {
            $client = $this->s3Client($diskName);
            $bucket = $this->bucket($diskName);

            $result = $client->createMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $path,
                'ContentType' => $mime,
            ]);

            $s3UploadId = $result['UploadId'];
            $partCount = (int) max(1, (int) ceil($size / $chunkSize));
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
            $this->missingPartNumbers($upload, $partCount),
        );

        return [
            'upload' => $upload,
            'strategy' => $strategy,
            'chunk_size' => $strategy === AttachmentUpload::STRATEGY_MULTIPART ? $chunkSize : $size,
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
            'chunk_size' => $upload->strategy === AttachmentUpload::STRATEGY_MULTIPART
                ? (int) config('attachments.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'recorded_parts' => $upload->recordedParts(),
            'parts' => $this->presignParts(
                $upload,
                $this->missingPartNumbers($upload, $partCount),
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

        if ($upload->strategy !== AttachmentUpload::STRATEGY_MULTIPART) {
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

        if ($upload->strategy !== AttachmentUpload::STRATEGY_MULTIPART) {
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

        if ($upload->strategy === AttachmentUpload::STRATEGY_SINGLE) {
            if (! $this->attachmentStorage->pathExists($upload->path, $upload->disk)) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.object_missing'));
            }
        } else {
            $merged = $this->mergeParts($upload, $parts);
            $this->assertAllPartsPresent($upload, $merged);
            $this->completeMultipartUpload($upload, $merged);
        }

        $upload->forceFill([
            'status' => AttachmentUpload::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'parts' => $upload->strategy === AttachmentUpload::STRATEGY_MULTIPART
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

        if ($upload->strategy === AttachmentUpload::STRATEGY_MULTIPART && $upload->s3_upload_id) {
            $client = $this->s3Client($upload->disk);
            $bucket = $this->bucket($upload->disk);

            $client->abortMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $upload->path,
                'UploadId' => $upload->s3_upload_id,
            ]);
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
        if ($size <= 0) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.invalid_size'));
        }

        $maxSize = (int) config('attachments.max_file_size_bytes');

        if ($size > $maxSize) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.file_too_large'));
        }

        if (trim($name) === '') {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.invalid_name'));
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
        if ($upload->strategy === AttachmentUpload::STRATEGY_SINGLE) {
            return 1;
        }

        $chunkSize = (int) config('attachments.chunk_size_bytes');

        return (int) max(1, (int) ceil($upload->size / $chunkSize));
    }

    /**
     * @return list<int>
     */
    private function missingPartNumbers(AttachmentUpload $upload, int $partCount): array
    {
        $recorded = array_column($upload->recordedParts(), 'part_number');
        $missing = [];

        for ($part = 1; $part <= $partCount; $part++) {
            if (! in_array($part, $recorded, true)) {
                $missing[] = $part;
            }
        }

        $limit = (int) config('attachments.max_presigned_parts_per_response');

        return array_slice($missing, 0, $limit);
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     */
    private function presignParts(AttachmentUpload $upload, array $partNumbers): array
    {
        if ($partNumbers === []) {
            return [];
        }

        $disk = $this->diskAdapter($upload->disk);
        $expiresAt = Carbon::now()->addMinutes((int) config('attachments.presign_ttl_minutes'));
        $signed = [];

        if ($upload->strategy === AttachmentUpload::STRATEGY_SINGLE) {
            $payload = $disk->temporaryUploadUrl($upload->path, $expiresAt, [
                'ContentType' => $upload->mime,
            ]);

            return [[
                'part_number' => 1,
                'url' => $payload['url'],
                'headers' => $payload['headers'] ?? [],
            ]];
        }

        $client = $disk->getClient();
        $bucket = $this->bucket($upload->disk);

        foreach ($partNumbers as $partNumber) {
            $command = $client->getCommand('UploadPart', [
                'Bucket' => $bucket,
                'Key' => $upload->path,
                'UploadId' => $upload->s3_upload_id,
                'PartNumber' => $partNumber,
            ]);

            $request = $client->createPresignedRequest($command, $expiresAt);
            $signed[] = [
                'part_number' => $partNumber,
                'url' => (string) $request->getUri(),
                'headers' => $request->getHeaders(),
            ];
        }

        return $signed;
    }

    /**
     * @param  list<array{part_number: int, etag: string, size?: int}>|null  $incoming
     * @return list<array{part_number: int, etag: string, size: int}>
     */
    private function mergeParts(AttachmentUpload $upload, ?array $incoming): array
    {
        $byNumber = [];

        foreach ($upload->recordedParts() as $part) {
            $byNumber[(int) $part['part_number']] = [
                'part_number' => (int) $part['part_number'],
                'etag' => trim((string) $part['etag'], '"'),
                'size' => (int) ($part['size'] ?? 0),
            ];
        }

        foreach ($incoming ?? [] as $part) {
            $partNumber = (int) ($part['part_number'] ?? 0);
            $etag = trim((string) ($part['etag'] ?? ''), '"');

            if ($partNumber < 1 || $etag === '') {
                continue;
            }

            $byNumber[$partNumber] = [
                'part_number' => $partNumber,
                'etag' => $etag,
                'size' => (int) ($part['size'] ?? 0),
            ];
        }

        ksort($byNumber);

        return array_values($byNumber);
    }

    /**
     * @param  list<array{part_number: int, etag: string, size: int}>  $parts
     */
    private function assertAllPartsPresent(AttachmentUpload $upload, array $parts): void
    {
        $expected = $this->expectedPartCount($upload);

        if (count($parts) !== $expected) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.incomplete_parts'));
        }

        for ($part = 1; $part <= $expected; $part++) {
            $found = false;

            foreach ($parts as $record) {
                if ((int) $record['part_number'] === $part) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                throw new AttachmentUploadException(__('api/playground-attachment-uploads.incomplete_parts'));
            }
        }
    }

    /**
     * @param  list<array{part_number: int, etag: string, size: int}>  $parts
     */
    private function completeMultipartUpload(AttachmentUpload $upload, array $parts): void
    {
        $client = $this->s3Client($upload->disk);
        $bucket = $this->bucket($upload->disk);

        $client->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $upload->path,
            'UploadId' => $upload->s3_upload_id,
            'MultipartUpload' => [
                'Parts' => array_map(
                    fn (array $part): array => [
                        'ETag' => '"'.$part['etag'].'"',
                        'PartNumber' => $part['part_number'],
                    ],
                    $parts,
                ),
            ],
        ]);
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

    private function diskAdapter(string $diskName): AwsS3V3Adapter
    {
        $disk = Storage::disk($diskName);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.unsupported_disk'));
        }

        return $disk;
    }

    private function s3Client(string $diskName): S3Client
    {
        return $this->diskAdapter($diskName)->getClient();
    }

    private function bucket(string $diskName): string
    {
        $bucket = config('filesystems.disks.'.$diskName.'.bucket');

        if (! is_string($bucket) || $bucket === '') {
            throw new AttachmentUploadException(__('api/playground-attachment-uploads.unsupported_disk'));
        }

        return $bucket;
    }
}
