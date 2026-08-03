<?php

namespace App\Domain\Microstock\Services;

use App\Domain\FileHosting\Enums\FileVariantKind;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\FileVariantGenerator;
use App\Domain\FileHosting\Services\FileVariantService;
use Illuminate\Support\Facades\Log;

/**
 * Builds a small derivative of a microstock asset for the AI vision stage.
 * Prefers persisted file variants; falls back to ephemeral resize from original.
 */
class AssetResizeService
{
    public function __construct(
        private readonly FileVariantService $variants,
        private readonly FileVariantGenerator $generator,
    ) {}

    /**
     * Return a data-URI (base64) JPEG preview of the asset.
     */
    public function previewDataUri(HostedFile $file, ?FileVariantKind $kind = null): string
    {
        $kind ??= $this->defaultKind();

        $variant = $this->variants->findReady($file, $kind);
        if ($variant !== null) {
            try {
                $bytes = $this->variants->readVariantBytes($variant);

                return 'data:image/jpeg;base64,'.base64_encode($bytes);
            } catch (\Throwable $e) {
                Log::info('microstock.variants.read_failed', [
                    'file_id' => $file->id,
                    'kind' => $kind->value,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('microstock.variants.not_ready', [
                'file_id' => $file->id,
                'kind' => $kind->value,
                'delivery' => 'base64',
                'fallback' => 'ephemeral_resize',
            ]);
        }

        return $this->ephemeralDataUri($file, $kind->maxPx());
    }

    private function ephemeralDataUri(HostedFile $file, int $maxPx): string
    {
        $source = $this->generator->readOriginalBytes($file);
        $encoded = $this->generator->encodeJpeg($source, $maxPx);

        if (strlen($encoded['bytes']) > (int) setting_config('microstock.ai.max_request_image_bytes', 200_000)) {
            Log::warning('microstock.ai.preview_oversize', [
                'file_id' => $file->id,
                'bytes' => strlen($encoded['bytes']),
                'max_px' => $encoded['max_px'],
                'quality' => $encoded['quality'],
            ]);
        }

        return 'data:image/jpeg;base64,'.base64_encode($encoded['bytes']);
    }

    private function defaultKind(): FileVariantKind
    {
        $configured = (string) config('microstock.ai.vision_image.variant_kind', FileVariantKind::Ai1024->value);

        return FileVariantKind::tryFrom($configured) ?? FileVariantKind::Ai1024;
    }
}
