<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Services\SignedDownloadService;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs vision -> thinking AI metadata for a microstock asset, off the request
 * path. Terminal failures are only reported: metadata is best-effort and the
 * user can re-trigger analysis.
 */
class AnalyzeAssetMetadata implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $metadataId,
        private readonly ?string $hint = null,
    ) {}

    public function handle(AiMetadataService $ai, SignedDownloadService $downloads): void
    {
        $metadata = AssetMetadata::query()->with('file')->find($this->metadataId);

        if ($metadata === null || $metadata->file === null) {
            return;
        }

        // Idempotent: skip if already curated/finalized by a prior run.
        if ($metadata->status === MetadataStatus::Finalized) {
            return;
        }

        // Inline (preview-safe) signed URL the vision provider can fetch.
        $url = $downloads->resolve($metadata->file, inline: true)['url'];

        $result = $ai->generate($url, $this->hint);

        $metadata->forceFill([
            'title' => $result['title'],
            'keywords' => $result['keywords'],
            'ai_raw' => $result['raw'],
            'status' => MetadataStatus::Generated,
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
