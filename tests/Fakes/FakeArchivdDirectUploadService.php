<?php

namespace Tests\Fakes;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\Archivd\Services\AccessService;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Archivd\Support\UniqueDisplayNameAllocator;
use App\Domain\Archivd\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Test double for Archivd direct uploads on a faked disk (no real S3).
 */
class FakeArchivdDirectUploadService extends DirectUploadService
{
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
        $access = app(AccessService::class);
        $workspaceAccess = app(WorkspaceAccessService::class);

        if ($workspaceId !== null && $workspaceId !== '') {
            $workspace = $workspaceAccess->findOrFail($workspaceId);
            $workspaceAccess->assertCanMutate($workspace, $actor);
            $ownerId = $workspaceAccess->storageOwnerId($workspace);
            $wsId = $workspace->id;
        } else {
            $ownerId = $access->resolveOwnerId($actor, $userId);
            $owner = User::query()->findOrFail($ownerId);
            $access->assertCanMutateOwner($actor, $owner);
            $wsId = null;
        }

        // Mirror the real service: Microstock collections gate (no root upload,
        // no upload into a locked collection).
        if (($workspace ?? null)?->kind === WorkspaceKind::Microstock) {
            if ($folderId === null) {
                throw new ArchivdException(__('web/archivd.microstock.error_requires_folder'), 422);
            }
            $collection = app(FolderRepositoryInterface::class)
                ->findOwnedOrFail($ownerId, $folderId, $wsId);
            if ($collection->isCollectionLocked()) {
                throw new ArchivdException(__('web/archivd.microstock.error_collection_locked'), 422);
            }
        }

        $this->assertUploadAllowed($name, $mime, $size);
        $this->assertUnderConcurrentUploadLimit($ownerId);
        app(QuotaService::class)->assertCanAccept($ownerId, $size);

        $keys = app(ObjectKeyResolver::class);
        $objectId = $keys->generateObjectId();

        $threshold = (int) config('archivd.multipart_threshold_bytes');
        $chunkSize = (int) config('archivd.chunk_size_bytes');
        $strategy = $size < $threshold
            ? FileUpload::STRATEGY_SINGLE
            : FileUpload::STRATEGY_MULTIPART;
        $partCount = $strategy === FileUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($size / $chunkSize))
            : 1;

        $upload = FileUpload::query()->create([
            'user_id' => $ownerId,
            'workspace_id' => $wsId,
            'folder_id' => $folderId,
            'disk' => $this->resolveDiskName($disk),
            'path' => $objectId,
            's3_upload_id' => $strategy === FileUpload::STRATEGY_MULTIPART ? 'fake-upload-id' : null,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            'chunk_size' => $strategy === FileUpload::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $parts = [];

        for ($part = 1; $part <= min($partCount, 100); $part++) {
            $parts[] = [
                'part_number' => $part,
                'url' => 'https://fake-storage.test/put/'.$upload->id.'/'.$part,
                'headers' => [],
            ];
        }

        return [
            'upload' => $upload,
            'strategy' => $strategy,
            'chunk_size' => $strategy === FileUpload::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'parts' => $parts,
        ];
    }

    public function complete(User $actor, string $uploadUuid, ?array $parts = null): array
    {
        return DB::transaction(function () use ($actor, $uploadUuid, $parts): array {
            $upload = FileUpload::query()
                ->whereKey($uploadUuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($upload->status === FileUpload::STATUS_COMPLETED) {
                throw new ArchivdException(
                    __('api/archivd.upload.already_promoted'),
                    422,
                );
            }

            if ($upload->status !== FileUpload::STATUS_PENDING) {
                throw new ArchivdException(
                    __('api/archivd.upload.not_pending'),
                    422,
                );
            }

            if ($upload->isExpired()) {
                throw new ArchivdException(
                    __('api/archivd.upload.expired'),
                    422,
                );
            }

            app(QuotaService::class)->assertCanAccept($upload->user_id, $upload->size, $upload->id);

            $objectKey = app(ObjectKeyResolver::class)->objectKey($upload->user_id, $upload->path);
            Storage::disk($upload->disk)->put($objectKey, 'fake-binary');

            $displayName = app(UniqueDisplayNameAllocator::class)->allocateFile(
                $upload->user_id,
                $upload->folder_id,
                $upload->original_name,
                $upload->workspace_id,
            );

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
                'parts' => $parts ?? [],
                'promoted_file_id' => $file->id,
            ])->save();

            if ($upload->workspace_id !== null) {
                $workspace = app(WorkspaceAccessService::class)->findOrFail($upload->workspace_id);
                app(WorkspaceActivityRecorder::class)->fileCreated($workspace, $actor, $file);
            }

            return [
                'upload' => $upload->refresh(),
                'file' => $file,
            ];
        });
    }

    public function forceAbortPending(FileUpload $upload): void
    {
        if ($upload->status !== FileUpload::STATUS_PENDING) {
            return;
        }

        // Mirror the real service: a single-PUT object is preserved on abort (no
        // dangling multipart); only multipart sessions have S3 state to clean up.
        if ($upload->strategy === FileUpload::STRATEGY_MULTIPART) {
            $objectKey = app(ObjectKeyResolver::class)->objectKey($upload->user_id, $upload->path);
            Storage::disk($upload->disk)->delete($objectKey);
        }

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
    }

    public function status(User $actor, string $uploadUuid): array
    {
        $upload = FileUpload::query()->findOrFail($uploadUuid);
        $partCount = $upload->strategy === FileUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($upload->size / (int) config('archivd.chunk_size_bytes')))
            : 1;

        return [
            'upload' => $upload,
            'strategy' => $upload->strategy,
            'chunk_size' => $upload->strategy === FileUpload::STRATEGY_MULTIPART
                ? (int) config('archivd.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'parts' => [[
                'part_number' => 1,
                'url' => 'https://fake-storage.test/put/'.$upload->id.'/1',
                'headers' => [],
            ]],
        ];
    }

    public function signParts(User $actor, string $uploadUuid, array $partNumbers): array
    {
        return array_map(
            fn (int $partNumber): array => [
                'part_number' => $partNumber,
                'url' => 'https://fake-storage.test/put/'.$uploadUuid.'/'.$partNumber,
                'headers' => [],
            ],
            $partNumbers,
        );
    }

    public function abort(User $actor, string $uploadUuid): void
    {
        $upload = FileUpload::query()->find($uploadUuid);

        if ($upload === null || $upload->status !== FileUpload::STATUS_PENDING) {
            return;
        }

        // Mirror the real service: preserve a single-PUT object on abort.
        if ($upload->strategy === FileUpload::STRATEGY_MULTIPART) {
            $objectKey = app(ObjectKeyResolver::class)->objectKey($upload->user_id, $upload->path);
            Storage::disk($upload->disk)->delete($objectKey);
        }

        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
    }
}
