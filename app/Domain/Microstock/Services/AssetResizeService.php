<?php

namespace App\Domain\Microstock\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Builds a small derivative of a microstock asset for the AI vision stage.
 * The original file stays untouched for the marketplace; the AI only needs a
 * compact preview so the gateway responds fast (avoids the 60s cURL cutoff
 * seen when full-size originals were sent).
 */
class AssetResizeService
{
    public function __construct(
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
    ) {}

    /**
     * Return a data-URI (base64) JPEG preview of the asset, downscaled so the
     * longest side is at most config('microstock.ai.image_max_px').
     */
    public function previewDataUri(HostedFile $file): string
    {
        $maxPx = (int) config('microstock.ai.image_max_px', 1024);
        $bytes = $this->readOriginal($file);

        $manager = new ImageManager(new GdDriver);
        $image = $manager->decode($bytes);
        $image->scaleDown($maxPx, $maxPx);

        $encoded = $image->encode(new JpegEncoder(quality: 80));

        return 'data:image/jpeg;base64,'.base64_encode((string) $encoded);
    }

    private function readOriginal(HostedFile $file): string
    {
        $key = $this->objectKeys->objectKey((string) $file->user_id, (string) $file->path);
        $bytes = Storage::disk($file->disk)->get($key);

        if (! is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Unable to read asset bytes for resize.');
        }

        return $bytes;
    }
}
