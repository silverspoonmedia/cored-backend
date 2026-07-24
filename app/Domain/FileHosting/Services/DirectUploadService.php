<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Support\FileHostingRealtime;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\FileHosting\Support\ObjectStorage;
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

        $this->assertUploadAllowed($name, $mime, $size);
        // Cap concurrent pending sessions against storage owner (same row as quota).
        $this->assertUnderConcurrentUploadLimit($ownerId);
        // Display-name collisions are resolved at complete (auto "name (N).ext"), not rejected here.
        // S3 object keys stay unique via random object id regardless of display name.
        // Team files always charge the workspace owner's quota (pending sessions reserved).
        $this->quota->assertCanAccept($ownerId, $size);

        $diskName = $this->resolveDiskName($disk);
        $objectId = $this->objectKeys->generateObjectId();
        $objectKey = $this->objectKeys->objectKey($ownerId, $objectId);
        $expiresAt = Carbon::now()->addHours((int) config('file-hosting.session_ttl_hours'));

        $threshold = (int) config('file-hosting.multipart_threshold_bytes');
        $chunkSize = (int) config('file-hosting.chunk_size_bytes');
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
            $displayName = $this->allocateUniqueDisplayName(
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
        } elseif ($this->storage->pathExists($objectKey, $upload->disk)) {
            $this->storage->deleteMany([['path' => $objectKey, 'disk' => $upload->disk]]);
        }

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
        $this->realtime->quotaChanged((string) $upload->user_id);
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

    /**
     * Server-side concurrent session cap for a storage owner (pending non-expired rows).
     */
    protected function assertUnderConcurrentUploadLimit(string $ownerUserId): void
    {
        $max = (int) config('file-hosting.max_concurrent_uploads', 0);
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
        } elseif ($this->storage->pathExists($objectKey, $upload->disk)) {
            $this->storage->deleteMany([['path' => $objectKey, 'disk' => $upload->disk]]);
        }

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
            $this->workspaceAccess->assertCanMutate($workspace, $actor);
            $this->workspaceAccess->assertOwnerEligibleForCollab($workspace->owner_user_id);

            return [$this->workspaceAccess->storageOwnerId($workspace), $workspace];
        }

        $ownerId = $this->access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $this->access->assertCanMutateOwner($actor, $owner);

        return [$ownerId, null];
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
                $this->objectKeys->objectKey($upload->user_id, $upload->path),
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

    /**
     * Pick a free sibling display name for promote-to-file.
     *
     * Soft-deleted rows are ignored (default SoftDeletes scope). S3 keys are never renamed.
     *
     * Rules (preferred = client original name, e.g. "A.zip"):
     * - If preferred free → use it.
     * - Else → "{stem} ({maxN+1}).{ext}" where maxN is the highest active index among
     *   preferred itself (counts as 1) and siblings matching "{stem} (N).{ext}".
     *   Example: A.zip + A (3).zip active → next is A (4).zip even if (2) was deleted.
     * - If A.zip and A (2).zip soft-deleted but A (3).zip remains → preferred free → A.zip again.
     */
    protected function allocateUniqueDisplayName(
        string $ownerId,
        ?string $folderId,
        string $preferred,
        ?string $workspaceId = null,
    ): string {
        $preferred = trim($preferred);

        if ($preferred === '') {
            throw new FileHostingException(__('api/file-hosting.upload.invalid_name'));
        }

        if (! $this->displayNameExists($ownerId, $folderId, $preferred, $workspaceId)) {
            return $preferred;
        }

        [$stem, $ext] = $this->splitDisplayName($preferred);
        $maxSequence = $this->maxDisplayNameSequence($ownerId, $folderId, $stem, $ext, $workspaceId);
        $next = max(2, $maxSequence + 1);

        for ($n = $next; $n < $next + 1000; $n++) {
            $candidate = $this->formatNumberedDisplayName($stem, $ext, $n);

            if (! $this->displayNameExists($ownerId, $folderId, $candidate, $workspaceId)) {
                return $candidate;
            }
        }

        throw new FileHostingException(__('api/file-hosting.file_name_taken'));
    }

    protected function displayNameExists(
        string $ownerId,
        ?string $folderId,
        string $name,
        ?string $workspaceId = null,
    ): bool {
        return HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    /**
     * Highest sequence in the "{stem}" / "{stem} (N)" family (same extension).
     * Base name counts as 1 when present. Soft-deleted ignored.
     *
     * @return int 0 when no family members exist
     */
    protected function maxDisplayNameSequence(
        string $ownerId,
        ?string $folderId,
        string $stem,
        string $ext,
        ?string $workspaceId = null,
    ): int {
        $base = $this->formatNumberedDisplayName($stem, $ext, null);
        $max = 0;

        $names = HostedFile::query()
            ->where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->pluck('name');

        $quotedStem = preg_quote($stem, '/');
        $pattern = $ext === ''
            ? '/^'.$quotedStem.' \((\d+)\)$/iu'
            : '/^'.$quotedStem.' \((\d+)\)\.'.preg_quote($ext, '/').'$/iu';

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (mb_strtolower($name) === mb_strtolower($base)) {
                $max = max($max, 1);

                continue;
            }

            if (preg_match($pattern, $name, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    /**
     * @return array{0: string, 1: string} stem, extension without leading dot (may be empty)
     */
    protected function splitDisplayName(string $name): array
    {
        $ext = (string) pathinfo($name, PATHINFO_EXTENSION);
        $stem = (string) pathinfo($name, PATHINFO_FILENAME);

        return [$stem, $ext];
    }

    /**
     * @param  int|null  $n  null = base name without " (N)"
     */
    protected function formatNumberedDisplayName(string $stem, string $ext, ?int $n): string
    {
        $body = $n === null ? $stem : $stem.' ('.$n.')';

        return $ext === '' ? $body : $body.'.'.$ext;
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
            S3DirectUploadException::UPSTREAM_FAILED => $e->getMessage() !== '' && $e->getMessage() !== S3DirectUploadException::UPSTREAM_FAILED
                ? __('api/file-hosting.upload.upstream_failed', ['detail' => $e->getMessage()])
                : __('api/file-hosting.upload.upstream_failed_generic'),
            default => $e->getMessage(),
        };

        return new FileHostingException($message);
    }
}
