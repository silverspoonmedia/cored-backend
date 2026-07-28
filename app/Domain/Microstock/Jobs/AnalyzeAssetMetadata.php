<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queues decoupled vision → thinking analysis for a single microstock asset.
 */
class AnalyzeAssetMetadata implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly string $metadataId,
        private readonly ?string $hint = null,
    ) {}

    public function handle(AnalysisStageService $stages): void
    {
        $metadata = AssetMetadata::query()->find($this->metadataId);

        if ($metadata === null) {
            return;
        }

        if ($metadata->status === MetadataStatus::Finalized) {
            return;
        }

        $stages->initializeStages($metadata);

        AnalyzeAssetVision::dispatchForSolo($this->metadataId, $this->hint, null);
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $metadata = AssetMetadata::query()->find($this->metadataId);
        if ($metadata === null) {
            return;
        }

        app(AnalysisStageService::class)->markVisionFailed($metadata, $e);
    }
}
