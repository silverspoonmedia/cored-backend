<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Jobs\ComputeHostedFileChecksum;
use App\Domain\FileHosting\Jobs\GenerateFileVariants;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FileHostingRealtime;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\FileHosting\Support\UniqueDisplayNameAllocator;
use App\Domain\FileHosting\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Browser-direct uploads to S3-compatible storage with ownership + quota + promote-to-file.
 *
 * Storage mechanics live in {@see S3DirectUploadEngine}; this service owns domain rules only.
 * DB `path` stores object id only; full S3 key is resolved via {@see ObjectKeyResolver}.
 * Team uploads bill owner quota; S3 key uses owner user id.
 */
class DirectUploadService
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly FolderRepositoryInterface $folders,
        private readonly AccessService $access,
        private readonly QuotaService $quota,
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceActivityRecorder $activity,
        private readonly FileHostingRealtime $realtime,
        private readonly UniqueDisplayNameAllocator $displayNames = new UniqueDisplayNameAllocator,
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
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
        ?string $workspaceId = null,
    ): array {
        [$ownerId, $workspace] = $this->resolveUploadScope($actor, $userId, $workspaceId);
        $wsId = $workspace?->id;

        if ($folderId !== null) {
            $this->folders->findOwnedOrFail($ownerId, $folderId, $wsId);
        }

        // Microstock collections: uploads must live inside a folder (collection),
        // never the workspace root, and a locked collection no longer accepts files.
        if ($workspace?->kind === WorkspaceKind::Microstock) {
            if ($folderId === null) {
                throw new FileHostingException(__('web/file-hosting.microstock.error_requires_folder'), 422);
            }
            $collection = $this->folders->findOwnedOrFail($ownerId, $folderId, $wsId);
            if ($collection->isCollectionLocked()) {
                throw new FileHostingException(__('web/file-hosting.microstock.error_collection_locked'), 422);
            }
            $this->assertMicrostockImage($mime);
        }

        $this->assertUploadAllowed($name, $mime, $size);
        $this->assertUnderConcurrentUploadLimit($ownerId);
        // Display-name collisions are resolved at complete (auto "name (N).ext"), not rejected here.
        // S3 object keys stay unique via random object id regardless of display name.
        // Team files always charge the workspace owner's quota (pending sessions reserved).
        $this->quota->assertCanAccept($ownerId, $size);

        $diskName = $this->resolveDiskName($disk);
        $objectId = $this->objectKeys->generateObjectId();
        $objectKey = $this->objectKeys->objectKey($ownerId, $objectId);
        $expiresAt = Carbon::now()->addHours((int) setting_config('file-hosting.session_ttl_hours'));

        $threshold = (int) setting_config('file-hosting.multipart_threshold_bytes');
        $chunkSize = (int) setting_config('file-hosting.chunk_size_bytes');
        $strategy = $this->engine->resolveStrategy($size, $threshold);
        $partCount = $this->engine->expectedPartCount($strategy, $size, $chunkSize);

        $s3UploadId = null;

        if ($strategy === S3DirectUploadEngine::STRATEGY_MULTIPART) {
            try {
                $s3UploadId = $this->engine->createMultipartUpload($diskName, $objectKey, $mime);
            } catch (S3DirectUploadException $e) {
                throw $this->mapEngineException($e);
            }
        }

        $upload = FileUpload::query()->create([
            'user_id' => $ownerId,
            'workspace_id' => $wsId,
            'folder_id' => $folderId,
            'disk' => $diskName,
            'path' => $objectId,
            's3_upload_id' => $s3UploadId,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            // Freeze chunk layout so a later config change never breaks part math.
            'chunk_size' => $strategy === S3DirectUploadEngine::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => $expiresAt,
        ]);

        $initialParts = $this->presignParts(
            $upload,
            range(1, $partCount),
        );

        $this->realtime->quotaChanged($ownerId);

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
            'chunk_size' => $this->resolveChunkSize($upload),
            'part_count' => $partCount,
            'parts' => $this->presignParts(
                $upload,
                range(1, $partCount),
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
        // Access check outside lock (cheap fail-fast); re-lock + re-validate inside txn.
        $this->findUsableUpload($actor, $uploadUuid);

        $result = DB::transaction(function () use ($actor, $uploadUuid, $parts): array {
            $upload = FileUpload::query()
                ->whereKey($uploadUuid)
                ->lockForUpdate()
                ->first();

            if ($upload === null) {
                throw new FileHostingException(__('api/file-hosting.upload.not_found'), 404);
            }

            if ($upload->status === FileUpload::STATUS_COMPLETED) {
                throw new FileHostingException(__('api/file-hosting.upload.already_promoted'), 422);
            }

            if ($upload->status !== FileUpload::STATUS_PENDING) {
                throw new FileHostingException(__('api/file-hosting.upload.not_pending'), 422);
            }

            if ($upload->isExpired()) {
                throw new FileHostingException(__('api/file-hosting.upload.expired'), 422);
            }

            // Re-assert mutate rights after lock (archive may have raced).
            $this->assertCanCompleteUpload($actor, $upload);

            // Exclude this session from pending reservation (it is the incoming size).
            $this->quota->assertCanAccept($upload->user_id, $upload->size, $upload->id);

            // Allocate DB display name before irreversible S3 complete so a later
            // rename failure cannot leave multipart finalized with no HostedFile row.
            $displayName = $this->displayNames->allocateFile(
                $upload->user_id,
                $upload->folder_id,
                $upload->original_name,
                $upload->workspace_id,
            );

            $merged = $upload->parts;
            $objectKey = $this->objectKeys->objectKey($upload->user_id, $upload->path);

            if ($upload->strategy === S3DirectUploadEngine::STRATEGY_SINGLE) {
                if (! $this->storage->pathExists($objectKey, $upload->disk)) {
                    throw new FileHostingException(__('api/file-hosting.upload.object_missing'));
                }
            } else {
                $merged = $this->engine->mergeParts($upload->recordedParts(), $parts);

                try {
                    $this->engine->assertAllPartsPresent($merged, $this->expectedPartCount($upload));
                    $this->engine->completeMultipartUpload(
                        $upload->disk,
                        $objectKey,
                        (string) $upload->s3_upload_id,
                        $merged,
                    );
                } catch (S3DirectUploadException $e) {
                    throw $this->mapEngineException($e);
                }
            }

            $file = HostedFile::query()->create([
                'user_id' => $upload->user_id,
                'workspace_id' => $upload->workspace_id,
                'created_by' => $actor->id,
                'folder_id' => $upload->folder_id,
                'name' => $displayName,
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

            if ($upload->workspace_id !== null) {
                $workspace = $this->workspaceAccess->findOrFail($upload->workspace_id);
                $this->activity->fileCreated($workspace, $actor, $file);
            }

            return [
                'upload' => $upload->refresh(),
                'file' => $file,
            ];
        });

        // Broadcast after commit so peers never see a listing for a rolled-back row.
        $this->realtime->fileListing('create', $result['file'], $actor);
        $this->realtime->quotaChanged((string) $result['file']->user_id);

        // Checksum (sha256) computed in the background, post-upload: keeps the
        // request path light and never blocks complete. Drives dedup + DMCA
        // hash blocklist. Idempotent — safe if re-dispatched.
        ComputeHostedFileChecksum::dispatch((string) $result['file']->id);

        if ($this->shouldGenerateVariants($result['upload'], $result['file'])) {
            GenerateFileVariants::dispatch((string) $result['file']->id);
        }

        return $result;
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

        $objectKey = $this->objectKeys->objectKey($upload->user_id, $upload->path);

        if ($upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART && $upload->s3_upload_id) {
            try {
                $this->engine->abortMultipartUpload(
                    $upload->disk,
                    $objectKey,
                    (string) $upload->s3_upload_id,
                );
            } catch (S3DirectUploadException $e) {
                throw $this->mapEngineException($e);
            }
        }
        // Single-PUT: leave the object in place. A single upload has no dangling
        // multipart on S3, and a late complete() may still promote it (the prune
        // scheduler can abort a slow single PUT while the client is mid-flight).

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
        $this->realtime->quotaChanged((string) $upload->user_id);
    }

    /**
     * Microstock collections only accept raster images the AI vision stage can
     * read. Anything else is rejected up-front so it never reaches analysis.
     */
    protected function shouldGenerateVariants(FileUpload $upload, HostedFile $file): bool
    {
        if ($upload->workspace_id === null) {
            return false;
        }

        $workspace = Workspace::query()->find($upload->workspace_id);
        if ($workspace?->kind !== WorkspaceKind::Microstock) {
            return false;
        }

        $allowed = (array) config('microstock.allowed_image_mimes', []);

        return in_array(strtolower((string) $file->mime), $allowed, true);
    }

    protected function assertMicrostockImage(string $mime): void
    {
        $allowed = (array) config('microstock.allowed_image_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);

        if (! in_array(strtolower($mime), $allowed, true)) {
            throw new FileHostingException(__('web/file-hosting.microstock.error_image_only'), 422);
        }
    }

    protected function assertUploadAllowed(string $name, string $mime, int $size): void
    {
        $prefixes = setting_config('file-hosting.allowed_mime_prefixes', []);
        $prefixes = is_array($prefixes) ? $prefixes : [];

        try {
            $this->engine->assertUploadAllowed(
                $name,
                $mime,
                $size,
                (int) setting_config('file-hosting.max_file_size_bytes'),
                $prefixes,
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
        }
    }

    /**
     * Server-side concurrent session cap for a storage owner (pending non-expired rows).
     */
    protected function assertUnderConcurrentUploadLimit(string $ownerUserId): void
    {
        $max = (int) setting_config('file-hosting.max_concurrent_uploads', 0);
        if ($max <= 0) {
            return;
        }

        $count = FileUpload::query()
            ->where('user_id', $ownerUserId)
            ->where('status', FileUpload::STATUS_PENDING)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($count >= $max) {
            throw new FileHostingException(__('api/file-hosting.upload.too_many_concurrent'), 422);
        }
    }

    /**
     * Best-effort abort all pending uploads for a workspace (e.g. on archive).
     */
    public function abortPendingForWorkspace(string $workspaceId): int
    {
        $uploads = FileUpload::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', FileUpload::STATUS_PENDING)
            ->get();

        $aborted = 0;
        foreach ($uploads as $upload) {
            $this->forceAbortPending($upload);
            $aborted++;
        }

        return $aborted;
    }

    /**
     * Abort without actor ACL (system / archive path).
     */
    public function forceAbortPending(FileUpload $upload): void
    {
        if ($upload->status !== FileUpload::STATUS_PENDING) {
            return;
        }

        $objectKey = $this->objectKeys->objectKey($upload->user_id, $upload->path);

        if ($upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART && $upload->s3_upload_id) {
            try {
                $this->engine->abortMultipartUpload(
                    $upload->disk,
                    $objectKey,
                    (string) $upload->s3_upload_id,
                );
            } catch (S3DirectUploadException) {
                // Best-effort: still mark aborted so complete cannot promote.
            }
        }
        // Single-PUT: preserve the object (no dangling multipart); the scheduler
        // may abort a slow single PUT that is still legitimately in flight.

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
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

        if (! $this->objectKeys->isValidObjectId($upload->path)) {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_path'));
        }

        return $upload;
    }

    private function assertCanCompleteUpload(User $actor, FileUpload $upload): void
    {
        $this->assertCanAccessUpload($actor, $upload, mutate: true);

        if (! $this->objectKeys->isValidObjectId($upload->path)) {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_path'));
        }
    }

    private function assertCanAccessUpload(User $actor, FileUpload $upload, bool $mutate): void
    {
        if ($upload->workspace_id !== null) {
            $workspace = $this->workspaceAccess->findOrFail($upload->workspace_id);
            if ($mutate) {
                $this->workspaceAccess->assertCanMutate($workspace, $actor);
            } else {
                $this->workspaceAccess->assertCanView($workspace, $actor);
            }

            return;
        }

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

    /**
     * @return array{0: string, 1: Workspace|null}
     */
    private function resolveUploadScope(User $actor, ?string $userId, ?string $workspaceId): array
    {
        if ($workspaceId !== null && $workspaceId !== '') {
            $workspace = $this->workspaceAccess->findOrFail($workspaceId);
            // Upload/collaboration only needs mutate access (owner or active
            // member). The paid gate (assertOwnerEligibleForCollab) is for
            // sharing/creating workspaces, not for putting content into one.
            $this->workspaceAccess->assertCanMutate($workspace, $actor);

            return [$this->workspaceAccess->storageOwnerId($workspace), $workspace];
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $this->access->assertCanMutateOwner($actor, $owner);

        return [$ownerId, null];
    }

    private function expectedPartCount(FileUpload $upload): int
    {
        // Prefer the count frozen at init; recompute only for legacy rows.
        if ($upload->part_count !== null && $upload->part_count > 0) {
            return (int) $upload->part_count;
        }

        return $this->engine->expectedPartCount(
            $upload->strategy,
            $upload->size,
            $this->resolveChunkSize($upload),
        );
    }

    /**
     * Chunk size frozen at init (survives config drift); config fallback for legacy rows.
     */
    private function resolveChunkSize(FileUpload $upload): int
    {
        if ($upload->chunk_size !== null && $upload->chunk_size > 0) {
            return (int) $upload->chunk_size;
        }

        return $upload->strategy === S3DirectUploadEngine::STRATEGY_MULTIPART
            ? (int) setting_config('file-hosting.chunk_size_bytes')
            : $upload->size;
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
                $this->objectKeys->objectKey($upload->user_id, $upload->path),
                $upload->strategy,
                $upload->mime,
                $upload->s3_upload_id,
                $partNumbers,
                (int) setting_config('file-hosting.presign_ttl_minutes'),
            );
        } catch (S3DirectUploadException $e) {
            throw $this->mapEngineException($e);
        }
    }

    /**
     * Resolve a requested region key to its configured disk name.
     * Unknown/missing keys fall back to the default file-hosting disk.
     */
    protected function resolveDiskName(?string $requested): string
    {
        $regions = (array) setting_config('file-hosting.regions');

        if ($requested !== null && $requested !== '' && isset($regions[$requested])) {
            return (string) $regions[$requested];
        }

        return (string) setting_config('file-hosting.disk');
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
            S3DirectUploadException::UPSTREAM_FAILED => $e->getMessage() !== '' && $e->getMessage() !== S3DirectUploadException::UPSTREAM_FAILED
                ? __('api/file-hosting.upload.upstream_failed', ['detail' => $e->getMessage()])
                : __('api/file-hosting.upload.upstream_failed_generic'),
            default => $e->getMessage(),
        };

        return new FileHostingException($message);
    }
}
