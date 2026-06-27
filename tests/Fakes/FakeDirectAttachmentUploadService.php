<?php

namespace Tests\Fakes;

use App\Models\AttachmentUpload;
use App\Support\Orders\DirectAttachmentUploadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test double that simulates direct uploads on a faked disk (no real S3 calls).
 */
class FakeDirectAttachmentUploadService extends DirectAttachmentUploadService
{
    public function initiate(string $name, string $mime, int $size): array
    {
        $this->assertUploadAllowed($name, $mime, $size);

        $uploadId = (string) Str::uuid();
        $directory = trim((string) config('attachments.directory'), '/');
        $safeName = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $path = $directory.'/'.$uploadId.'/'.$safeName.($extension !== '' ? '.'.$extension : '');

        $threshold = (int) config('attachments.multipart_threshold_bytes');
        $chunkSize = (int) config('attachments.chunk_size_bytes');
        $strategy = $size < $threshold
            ? AttachmentUpload::STRATEGY_SINGLE
            : AttachmentUpload::STRATEGY_MULTIPART;
        $partCount = $strategy === AttachmentUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($size / $chunkSize))
            : 1;

        $upload = AttachmentUpload::query()->create([
            'disk' => config('attachments.disk'),
            'path' => $path,
            's3_upload_id' => $strategy === AttachmentUpload::STRATEGY_MULTIPART ? 'fake-upload-id' : null,
            'strategy' => $strategy,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            'status' => AttachmentUpload::STATUS_PENDING,
            'parts' => [],
            'user_id' => auth()->id(),
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
            'chunk_size' => $strategy === AttachmentUpload::STRATEGY_MULTIPART ? $chunkSize : $size,
            'part_count' => $partCount,
            'parts' => $parts,
        ];
    }

    public function complete(string $uploadUuid, ?array $parts = null): array
    {
        $upload = AttachmentUpload::query()->findOrFail($uploadUuid);
        $disk = Storage::disk($upload->disk);
        $disk->put($upload->path, 'fake-binary');

        $upload->forceFill([
            'status' => AttachmentUpload::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'parts' => $parts ?? [],
        ])->save();

        return [
            'upload' => $upload->refresh(),
            'metadata' => [
                'disk' => $upload->disk,
                'path' => $upload->path,
                'name' => $upload->original_name,
                'size' => $upload->size,
                'mime' => $upload->mime,
                'user_id' => $upload->user_id,
            ],
        ];
    }

    public function status(string $uploadUuid): array
    {
        $upload = AttachmentUpload::query()->findOrFail($uploadUuid);
        $partCount = $upload->strategy === AttachmentUpload::STRATEGY_MULTIPART
            ? (int) max(1, (int) ceil($upload->size / (int) config('attachments.chunk_size_bytes')))
            : 1;

        $missing = [];

        for ($part = 1; $part <= $partCount; $part++) {
            $found = false;

            foreach ($upload->recordedParts() as $recorded) {
                if ((int) $recorded['part_number'] === $part) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missing[] = $part;
            }
        }

        $parts = [];

        foreach ($missing as $partNumber) {
            $parts[] = [
                'part_number' => $partNumber,
                'url' => 'https://fake-storage.test/put/'.$upload->id.'/'.$partNumber,
                'headers' => [],
            ];
        }

        return [
            'upload' => $upload,
            'strategy' => $upload->strategy,
            'chunk_size' => $upload->strategy === AttachmentUpload::STRATEGY_MULTIPART
                ? (int) config('attachments.chunk_size_bytes')
                : $upload->size,
            'part_count' => $partCount,
            'recorded_parts' => $upload->recordedParts(),
            'parts' => $parts,
        ];
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers: array}>
     */
    public function signParts(string $uploadUuid, array $partNumbers): array
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

    public function abort(string $uploadUuid): void
    {
        $upload = AttachmentUpload::query()->find($uploadUuid);

        if ($upload === null || $upload->status !== AttachmentUpload::STATUS_PENDING) {
            return;
        }

        Storage::disk($upload->disk)->delete($upload->path);
        $upload->forceFill(['status' => AttachmentUpload::STATUS_ABORTED])->save();
    }
}
