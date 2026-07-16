<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Browser-direct uploads to S3-compatible storage with ownership + quota + promote-to-file.
 */
class DirectUploadService
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly QuotaService $quota,
    ) {}

    /**
     * @return array{
     *     upload: FileUpload,
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     parts: list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     * }
     */
    public function initiate(
        User $actor,
        string $name,
        string $mime,
        int $size,
        ?string $folderId = null,
        ?string $userId = null,
    ): array {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $this->access->assertCanMutateOwner($actor, $owner);

        if ($folderId !== null) {
            $this->folders->findOwnedOrFail($ownerId, $folderId);
        }

        $this->assertUploadAllowed($name, $mime, $size);
        $this->quota->assertCanAccept($ownerId, $size);

        $diskName = (string) config('file-hosting.disk');
        $uploadId = (string) Str::uuid();
        $path = $this->buildPath($ownerId, $uploadId, $name);
        $expiresAt = Carbon::now()->addHours((int) config('file-hosting.session_ttl_hours'));

        $threshold = (int) config('file-hosting.multipart_threshold_bytes');
        $chunkSize = (int) config('file-hosting.chunk_size_bytes');
        $strategy = $size < $threshold
            ? FileUpload::STRATEGY_SINGLE
            : FileUpload::STRATEGY_MULTIPART;

        $s3UploadId = null;
        $partCount = 1;

        if ($strategy === FileUpload::STRATEGY_MULTIPART) {
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

        $upload = FileUpload::query()->create([
            'user_id' => $ownerId,
            'folder_id' => $folderId,
            'disk' => $diskName,
            'path' => $path,
            's3_upload_id' => $s3UploadId,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => $expiresAt,
        ]);

        $initialParts = $this->presignParts(
            $upload,
            $this->missingPartNumbers($upload, $partCount),
        );

        return [
            'upload' => $upload,
            'strategy' => $strategy,
            'chunk_size' => $strategy === FileUpload::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'parts' => $initialParts,
        ];
    }

    /**
     * @return array{
     *     upload: FileUpload,
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     recorded_parts: list<array{part_number: int, etag: string, size: int}>,
     *     parts: list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     * }
     */
    public function status(User $actor, string $uploadUuid): array
    {
        $upload = $this->findUsableUpload($actor, $uploadUuid);
        $partCount = $this->expectedPartCount($upload);

        return [
            'upload' => $upload,
            'strategy' => $upload->strategy,
            'chunk_size' => $upload->strategy === FileUpload::STRATEGY_MULTIPART
                ? (int) config('file-hosting.chunk_size_bytes')
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
    public function signParts(User $actor, string $uploadUuid, array $partNumbers): array
    {
        $upload = $this->findUsableUpload($actor, $uploadUuid);

        if ($upload->strategy !== FileUpload::STRATEGY_MULTIPART) {
            throw new FileHostingException(__('api/file-hosting.upload.multipart_only'));
        }

        $partCount = $this->expectedPartCount($upload);
        $valid = [];

        foreach ($partNumbers as $partNumber) {
            $partNumber = (int) $partNumber;

            if ($partNumber < 1 || $partNumber > $partCount) {
                throw new FileHostingException(__('api/file-hosting.upload.invalid_part_number'));
            }

            $valid[] = $partNumber;
        }

        return $this->presignParts($upload, array_values(array_unique($valid)));
    }

    /**
     * Complete upload and promote to HostedFile in one step.
     *
     * @param  list<array{part_number: int, etag: string}>|null  $parts
     * @return array{upload: FileUpload, file: HostedFile}
     */
    public function complete(User $actor, string $uploadUuid, ?array $parts = null): array
    {
        $upload = $this->findUsableUpload($actor, $uploadUuid);
        $this->quota->assertCanAccept($upload->user_id, $upload->size);

        return DB::transaction(function () use ($upload, $parts): array {
            $merged = $upload->parts;

            if ($upload->strategy === FileUpload::STRATEGY_SINGLE) {
                if (! $this->storage->pathExists($upload->path, $upload->disk)) {
                    throw new FileHostingException(__('api/file-hosting.upload.object_missing'));
                }
            } else {
                $merged = $this->mergeParts($upload, $parts);
                $this->assertAllPartsPresent($upload, $merged);
                $this->completeMultipartUpload($upload, $merged);
            }

            $this->assertUniqueFileName($upload->user_id, $upload->folder_id, $upload->original_name);

            $file = HostedFile::query()->create([
                'user_id' => $upload->user_id,
                'folder_id' => $upload->folder_id,
                'name' => $upload->original_name,
                'disk' => $upload->disk,
                'path' => $upload->path,
                'size' => $upload->size,
                'mime' => $upload->mime,
            ]);

            $upload->forceFill([
                'status' => FileUpload::STATUS_COMPLETED,
                'completed_at' => Carbon::now(),
                'parts' => $upload->strategy === FileUpload::STRATEGY_MULTIPART
                    ? $merged
                    : $upload->parts,
                'promoted_file_id' => $file->id,
            ])->save();

            return [
                'upload' => $upload->refresh(),
                'file' => $file,
            ];
        });
    }

    public function abort(User $actor, string $uploadUuid): void
    {
        $upload = FileUpload::query()->find($uploadUuid);

        if ($upload === null) {
            return;
        }

        $this->assertCanAccessUpload($actor, $upload, mutate: true);

        if ($upload->status !== FileUpload::STATUS_PENDING) {
            return;
        }

        if ($upload->strategy === FileUpload::STRATEGY_MULTIPART && $upload->s3_upload_id) {
            $client = $this->s3Client($upload->disk);
            $bucket = $this->bucket($upload->disk);

            $client->abortMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $upload->path,
                'UploadId' => $upload->s3_upload_id,
            ]);
        } elseif ($this->storage->pathExists($upload->path, $upload->disk)) {
            $this->storage->deleteMany([['path' => $upload->path, 'disk' => $upload->disk]]);
        }

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
    }

    protected function assertUploadAllowed(string $name, string $mime, int $size): void
    {
        if ($size <= 0) {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_size'));
        }

        $maxSize = (int) config('file-hosting.max_file_size_bytes');

        if ($size > $maxSize) {
            throw new FileHostingException(__('api/file-hosting.upload.file_too_large'));
        }

        if (trim($name) === '') {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_name'));
        }

        $prefixes = config('file-hosting.allowed_mime_prefixes', []);

        if (is_array($prefixes) && $prefixes !== []) {
            $ok = false;
            foreach ($prefixes as $prefix) {
                if (is_string($prefix) && $prefix !== '' && str_starts_with($mime, $prefix)) {
                    $ok = true;
                    break;
                }
            }

            if (! $ok) {
                throw new FileHostingException(__('api/file-hosting.upload.mime_not_allowed'));
            }
        }
    }

    private function buildPath(string $ownerId, string $uploadId, string $name): string
    {
        $directory = trim((string) config('file-hosting.directory'), '/');
        // Random object key — display name lives only in DB (Content-Disposition on download).
        $random = Str::lower(Str::random(40));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';

        return $directory.'/'.$ownerId.'/'.$uploadId.'/'.$random.($extension !== '' ? '.'.$extension : '');
    }

    private function isPathAllowed(string $path, string $ownerId): bool
    {
        $directory = trim((string) config('file-hosting.directory'), '/');
        $prefix = $directory.'/'.$ownerId.'/';

        return str_starts_with($path, $prefix);
    }

    private function findUsableUpload(User $actor, string $uploadUuid): FileUpload
    {
        $upload = FileUpload::query()->find($uploadUuid);

        if ($upload === null) {
            throw new FileHostingException(__('api/file-hosting.upload.not_found'), 404);
        }

        $this->assertCanAccessUpload($actor, $upload, mutate: true);

        if ($upload->status !== FileUpload::STATUS_PENDING) {
            throw new FileHostingException(__('api/file-hosting.upload.not_pending'));
        }

        if ($upload->isExpired()) {
            throw new FileHostingException(__('api/file-hosting.upload.expired'));
        }

        if ($upload->isPromoted()) {
            throw new FileHostingException(__('api/file-hosting.upload.already_promoted'));
        }

        if (! $this->isPathAllowed($upload->path, $upload->user_id)) {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_path'));
        }

        return $upload;
    }

    private function assertCanAccessUpload(User $actor, FileUpload $upload, bool $mutate): void
    {
        $owner = User::query()->find($upload->user_id);

        if ($owner === null) {
            throw new FileHostingException(__('api/file-hosting.owner_not_found'), 404);
        }

        if ($mutate) {
            $this->access->assertCanMutateOwner($actor, $owner);
        } else {
            $this->access->assertCanViewOwner($actor, $owner);
        }
    }

    private function expectedPartCount(FileUpload $upload): int
    {
        if ($upload->strategy === FileUpload::STRATEGY_SINGLE) {
            return 1;
        }

        $chunkSize = (int) config('file-hosting.chunk_size_bytes');

        return (int) max(1, (int) ceil($upload->size / $chunkSize));
    }

    /**
     * @return list<int>
     */
    private function missingPartNumbers(FileUpload $upload, int $partCount): array
    {
        $recorded = array_column($upload->recordedParts(), 'part_number');
        $missing = [];

        for ($part = 1; $part <= $partCount; $part++) {
            if (! in_array($part, $recorded, true)) {
                $missing[] = $part;
            }
        }

        $limit = (int) config('file-hosting.max_presigned_parts_per_response');

        return array_slice($missing, 0, $limit);
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     */
    private function presignParts(FileUpload $upload, array $partNumbers): array
    {
        if ($partNumbers === []) {
            return [];
        }

        $disk = $this->diskAdapter($upload->disk);
        $expiresAt = Carbon::now()->addMinutes((int) config('file-hosting.presign_ttl_minutes'));

        if ($upload->strategy === FileUpload::STRATEGY_SINGLE) {
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
        $signed = [];

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
    private function mergeParts(FileUpload $upload, ?array $incoming): array
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
    private function assertAllPartsPresent(FileUpload $upload, array $parts): void
    {
        $expected = $this->expectedPartCount($upload);

        if (count($parts) !== $expected) {
            throw new FileHostingException(__('api/file-hosting.upload.incomplete_parts'));
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
                throw new FileHostingException(__('api/file-hosting.upload.incomplete_parts'));
            }
        }
    }

    /**
     * @param  list<array{part_number: int, etag: string, size: int}>  $parts
     */
    private function completeMultipartUpload(FileUpload $upload, array $parts): void
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

    private function assertUniqueFileName(string $ownerId, ?string $folderId, string $name): void
    {
        $exists = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw new FileHostingException(__('api/file-hosting.file_name_taken'));
        }
    }

    private function diskAdapter(string $diskName): AwsS3V3Adapter
    {
        $disk = Storage::disk($diskName);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new FileHostingException(__('api/file-hosting.upload.unsupported_disk'));
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
            throw new FileHostingException(__('api/file-hosting.upload.unsupported_disk'));
        }

        return $bucket;
    }
}
