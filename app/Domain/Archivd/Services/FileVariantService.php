<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Enums\FileVariantKind;
use App\Domain\Archivd\Enums\FileVariantStatus;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\FileVariant;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Archivd\Support\ObjectStorage;
use Illuminate\Support\Facades\Log;

class FileVariantService
{
    public function __construct(
        private readonly FileVariantGenerator $generator,
        private readonly QuotaService $quota,
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
        private readonly ObjectStorage $storage = new ObjectStorage,
    ) {}

    public function findReady(HostedFile $file, FileVariantKind|string $kind): ?FileVariant
    {
        $kindValue = $kind instanceof FileVariantKind ? $kind->value : $kind;

        return FileVariant::query()
            ->where('file_id', $file->id)
            ->where('kind', $kindValue)
            ->ready()
            ->first();
    }

    public function readVariantBytes(FileVariant $variant): string
    {
        $variant->loadMissing('file');
        if ($variant->file === null) {
            throw new \RuntimeException('Variant parent file missing.');
        }

        $key = $this->objectKeys->objectKey((string) $variant->file->user_id, (string) $variant->path);
        $bytes = $this->storage->get($key, (string) $variant->disk);

        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Unable to read variant bytes.');
        }

        return $bytes;
    }

    public function variantBytesForUser(string $userId): int
    {
        return (int) FileVariant::query()
            ->ready()
            ->whereHas('file', fn ($q) => $q->where('user_id', $userId))
            ->sum('size');
    }

    public function generateForFile(HostedFile $file): void
    {
        foreach ($this->configuredPresets() as $preset) {
            $kind = FileVariantKind::tryFrom((string) ($preset['kind'] ?? ''));
            if ($kind === null) {
                continue;
            }

            $this->generateOne($file, $kind, (int) ($preset['max_px'] ?? $kind->maxPx()));
        }
    }

    private function generateOne(HostedFile $file, FileVariantKind $kind, int $maxPx): void
    {
        $variant = FileVariant::query()->firstOrCreate(
            ['file_id' => $file->id, 'kind' => $kind->value],
            [
                'disk' => $file->disk,
                'path' => $this->objectKeys->generateObjectId(),
                'size' => 0,
                'max_px' => $maxPx,
                'mime' => 'image/jpeg',
                'status' => FileVariantStatus::Pending,
            ],
        );

        if ($variant->status === FileVariantStatus::Ready) {
            return;
        }

        $estimateBytes = (int) setting_config('microstock.ai.max_request_image_bytes', 200_000);

        try {
            $this->quota->assertCanAccept((string) $file->user_id, $estimateBytes);
        } catch (ArchivdException $e) {
            $this->markFailed($variant, 'quota_exceeded: '.$e->getMessage());

            return;
        }

        try {
            $source = $this->generator->readOriginalBytes($file);
            $encoded = $this->generator->encodeJpeg($source, $maxPx);
            $objectKey = $this->objectKeys->objectKey((string) $file->user_id, (string) $variant->path);

            $this->storage->put($objectKey, $encoded['bytes'], (string) $file->disk);

            $variant->forceFill([
                'disk' => $file->disk,
                'size' => strlen($encoded['bytes']),
                'max_px' => $encoded['max_px'],
                'mime' => 'image/jpeg',
                'status' => FileVariantStatus::Ready,
                'error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $this->markFailed($variant, mb_substr($e->getMessage(), 0, 1000));
            report($e);
        }
    }

    private function markFailed(FileVariant $variant, string $error): void
    {
        $variant->forceFill([
            'status' => FileVariantStatus::Failed,
            'error' => $error,
        ])->save();

        Log::warning('archivd.variant.failed', [
            'file_id' => $variant->file_id,
            'kind' => $variant->kind instanceof FileVariantKind ? $variant->kind->value : $variant->kind,
            'error' => $error,
        ]);
    }

    /**
     * @return list<array{kind: string, max_px: int}>
     */
    private function configuredPresets(): array
    {
        $presets = config('microstock.variants', []);

        return is_array($presets) ? array_values($presets) : [];
    }
}
