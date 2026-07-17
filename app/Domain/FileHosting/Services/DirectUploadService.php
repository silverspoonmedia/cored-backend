<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Browser-direct uploads to S3-compatible storage with ownership + quota + promote-to-file.
 *
 * Storage mechanics live in {@see S3DirectUploadEngine}; this service owns domain rules only.
 */
class DirectUploadService
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly QuotaService $quota,
        private readonly S3DirectUploadEngine $engine = new S3DirectUploadEngine,
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
        ?string $disk = null,
    ): array {
        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $this->access->assertCanMutateOwner($actor, $owner);

        if ($folderId !== null) {
            $this->folders->findOwnedOrFail($ownerId, $folderId);
        }

        $this->assertUploadAllowed($name, $mime, $size);
        $this->quota->assertCanAccept($ownerId, $size);

        $diskName = $this->resolveDiskName($disk);
        $uploadId = (string) Str::uuid();
        $path = $this->buildPath($ownerId, $uploadId, $name);
        $expiresAt = Carbon::now()->addHours((int) config('file-hosting.session_ttl_hours'));

        $threshold = (int) config('file-hosting.multipart_threshold_bytes');
        $chunkSize = (int) config('file-hosting.chunk_size_bytes');
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
            $this->engine->missingPartNumbers(
                $upload->recordedParts(),
                $partCount,
                (int) config('file-hosting.max_presigned_parts_per_response'),
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
            'chunk_size' => $upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART
                ? (int) config('file-hosting.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'recorded_parts' => $upload->recordedParts(),
            'parts' => $this->presignParts(
                $upload,
                $this->engine->missingPartNumbers(
                    $upload->recordedParts(),
                    $partCount,
                    (int) config('file-hosting.max_presigned_parts_per_response'),
                ),
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

        if ($upload->strategy !== S3DirectUploadEngine::STRATEGY_MULTIPART) {
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

            if ($upload->strategy === S3DirectUploadEngine::STRATEGY_SINGLE) {
                if (! $this->storage->pathExists($upload->path, $upload->disk)) {
                    throw new FileHostingException(__('api/file-hosting.upload.object_missing'));
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
                'parts' => $upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART
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
        } elseif ($this->storage->pathExists($upload->path, $upload->disk)) {
            $this->storage->deleteMany([['path' => $upload->path, 'disk' => $upload->disk]]);
        }

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
    }

    protected function assertUploadAllowed(string $name, string $mime, int $size): void
    {
        $prefixes = config('file-hosting.allowed_mime_prefixes', []);
        $prefixes = is_array($prefixes) ? $prefixes : [];

        try {
            $this->engine->assertUploadAllowed(
                $name,
                $mime,
                $size,
                (int) config('file-hosting.max_file_size_bytes'),
                $prefixes,
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
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
        return $this->engine->expectedPartCount(
            $upload->strategy,
            $upload->size,
            (int) config('file-hosting.chunk_size_bytes'),
        );
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     */
    private function presignParts(FileUpload $upload, array $partNumbers): array
    {
        try {
            return $this->engine->presignParts(
                $upload->disk,
                $upload->path,
                $upload->strategy,
                $upload->mime,
                $upload->s3_upload_id,
                $partNumbers,
                (int) config('file-hosting.presign_ttl_minutes'),
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
        }
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

    /**
     * Resolve a requested region key to its configured disk name.
     * Unknown/missing keys fall back to the default file-hosting disk.
     */
    protected function resolveDiskName(?string $requested): string
    {
        $regions = (array) config('file-hosting.regions');

        if ($requested !== null && $requested !== '' && isset($regions[$requested])) {
            return (string) $regions[$requested];
        }

        return (string) config('file-hosting.disk');
    }

    private function mapEngineException(S3DirectUploadException $e): FileHostingException
    {
        $message = match ($e->reason) {
            S3DirectUploadException::UNSUPPORTED_DISK => __('api/file-hosting.upload.unsupported_disk'),
            S3DirectUploadException::INCOMPLETE_PARTS => __('api/file-hosting.upload.incomplete_parts'),
            S3DirectUploadException::INVALID_SIZE => __('api/file-hosting.upload.invalid_size'),
            S3DirectUploadException::FILE_TOO_LARGE => __('api/file-hosting.upload.file_too_large'),
            S3DirectUploadException::INVALID_NAME => __('api/file-hosting.upload.invalid_name'),
            S3DirectUploadException::MIME_NOT_ALLOWED => __('api/file-hosting.upload.mime_not_allowed'),
            S3DirectUploadException::PROXY_MISCONFIGURED => __('api/file-hosting.upload.proxy_misconfigured'),
            default => $e->getMessage(),
        };

        return new FileHostingException($message);
    }
}
