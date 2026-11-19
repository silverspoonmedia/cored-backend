<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Archivd\Enums\FileVariantKind;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\FileVariantService;
use App\Domain\Archivd\Services\SignedDownloadService;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the image reference sent to the AI vision stage (base64 data-URI
 * or signed HTTPS URL) from a persisted variant or ephemeral resize.
 */
class VisionImageResolver
{
    public function __construct(
        private readonly FileVariantService $variants,
        private readonly SignedDownloadService $downloads,
        private readonly AssetResizeService $resizer,
    ) {}

    public function resolve(
        HostedFile $file,
        ?string $deliveryOverride = null,
        ?string $variantKindOverride = null,
    ): string {
        $delivery = $deliveryOverride ?? (string) setting_config('microstock.ai.vision_image.delivery', 'base64');
        $kind = $this->resolveKind($variantKindOverride);

        if ($delivery === 'url') {
            $variant = $this->variants->findReady($file, $kind);
            if ($variant !== null) {
                return $this->downloads->resolveVariant($variant)['url'];
            }

            Log::info('microstock.variants.not_ready', [
                'file_id' => $file->id,
                'kind' => $kind->value,
                'delivery' => 'url',
                'fallback' => 'base64',
            ]);
        }

        return $this->resizer->previewDataUri($file, $kind);
    }

    public function payloadByteLength(string $imageReference): int
    {
        return strlen($imageReference);
    }

    private function resolveKind(?string $override): FileVariantKind
    {
        if ($override !== null && $override !== '') {
            $kind = FileVariantKind::tryFrom($override);
            if ($kind !== null) {
                return $kind;
            }
        }

        $configured = (string) setting_config('microstock.ai.vision_image.variant_kind', FileVariantKind::Ai1024->value);
        $kind = FileVariantKind::tryFrom($configured);

        return $kind ?? FileVariantKind::Ai1024;
    }
}
