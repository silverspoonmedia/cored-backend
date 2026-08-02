<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Encodes downscaled JPEG bytes from raw image data. Shared by persisted
 * variants and ephemeral AI previews.
 */
class FileVariantGenerator
{
    public function __construct(
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
    ) {}

    /**
     * @return array{bytes: string, max_px: int, quality: int}
     */
    public function encodeJpeg(string $sourceBytes, int $maxPx): array
    {
        $maxBytes = (int) setting_config('microstock.ai.max_request_image_bytes', 200_000);
        $quality = (int) setting_config('microstock.ai.image_jpeg_quality', 80);
        $quality = max(40, min(95, $quality));

        $manager = new ImageManager(new GdDriver);
        $image = $manager->decode($sourceBytes);
        $image->scaleDown($maxPx, $maxPx);

        $encoded = (string) $image->encode(new JpegEncoder(quality: $quality));
        $currentPx = $maxPx;

        while (strlen($encoded) > $maxBytes && $currentPx > 256) {
            $currentPx = (int) max(256, floor($currentPx * 0.75));
            $image->scaleDown($currentPx, $currentPx);
            $quality = max(40, $quality - 10);
            $encoded = (string) $image->encode(new JpegEncoder(quality: $quality));
        }

        return [
            'bytes' => $encoded,
            'max_px' => $currentPx,
            'quality' => $quality,
        ];
    }

    public function readOriginalBytes(HostedFile $file): string
    {
        $key = $this->objectKeys->objectKey((string) $file->user_id, (string) $file->path);
        $bytes = Storage::disk($file->disk)->get($key);

        if (! is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Unable to read asset bytes for resize.');
        }

        return $bytes;
    }
}
