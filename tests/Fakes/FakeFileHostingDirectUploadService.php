<?php

namespace Tests\Fakes;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\AccessService;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\QuotaService;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test double for File Hosting direct uploads on a faked disk (no real S3).
 */
class FakeFileHostingDirectUploadService extends DirectUploadService
{
    public function initiate(
        User $actor,
        string $name,
        string $mime,
        int $size,
        ?string $folderId = null,
        ?string $userId = null,
        ?string $disk = null,
    ): array {
        $access = app(AccessService::class);
        $ownerId = $access->resolveOwnerId($actor, $userId);
        $owner = User::query()->findOrFail($ownerId);
        $access->assertCanMutateOwner($actor, $owner);

        $this->assertUploadAllowed($name, $mime, $size);
        app(QuotaService::class)->assertCanAccept($ownerId, $size);

        $uploadId = (string) Str::uuid();
        $directory = trim((string) config('file-hosting.directory'), '/');
        $random = Str::lower(Str::random(40));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
        $path = $directory.'/'.$ownerId.'/'.$uploadId.'/'.$random.($extension !== '' ? '.'.$extension : '');

        $threshold = (int) config('file-hosting.multipart_threshold_bytes');
        $chunkSize = (int) config('file-hosting.chunk_size_bytes');
        $strategy = $size < $threshold
            ? FileUpload::STRATEGY_SINGLE
            : FileUpload::STRATEGY_MULTIPART;
        $partCount = $strategy === FileUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($size / $chunkSize))
            : 1;

        $upload = FileUpload::query()->create([
            'user_id' => $ownerId,
            'folder_id' => $folderId,
            'disk' => $this->resolveDiskName($disk),
            'path' => $path,
            's3_upload_id' => $strategy === FileUpload::STRATEGY_MULTIPART ? 'fake-upload-id' : null,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
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
        $upload = FileUpload::query()->findOrFail($uploadUuid);
        Storage::disk($upload->disk)->put($upload->path, 'fake-binary');

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
            'parts' => $parts ?? [],
            'promoted_file_id' => $file->id,
        ])->save();

        return [
            'upload' => $upload->refresh(),
            'file' => $file,
        ];
    }

    public function status(User $actor, string $uploadUuid): array
    {
        $upload = FileUpload::query()->findOrFail($uploadUuid);
        $partCount = $upload->strategy === FileUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($upload->size / (int) config('file-hosting.chunk_size_bytes')))
            : 1;

        return [
            'upload' => $upload,
            'strategy' => $upload->strategy,
            'chunk_size' => $upload->strategy === FileUpload::STRATEGY_MULTIPART
                ? (int) config('file-hosting.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'recorded_parts' => $upload->recordedParts(),
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

        Storage::disk($upload->disk)->delete($upload->path);
        $upload->forceFill(['status' => FileUpload::STATUS_ABORTED])->save();
    }
}
