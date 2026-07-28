<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Single-asset vision stage. Dispatches thinking on success.
 */
class AnalyzeAssetVision implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(
        private readonly string $metadataId,
        private readonly ?string $hint = null,
    ) {}

    public function handle(
        AiMetadataService $ai,
        VisionImageResolver $images,
        AnalysisStageService $stages,
    ): void {
        $metadata = AssetMetadata::query()->with('file')->find($this->metadataId);

        if ($metadata === null || $metadata->file === null) {
            return;
        }

        if ($metadata->status === MetadataStatus::Finalized) {
            return;
        }

        $stages->initializeStages($metadata->refresh());
        $stages->markVisionRunning($metadata->refresh());

        try {
            $image = $images->resolve($metadata->file);
            $folderId = $metadata->file->folder_id !== null ? (string) $metadata->file->folder_id : null;

            $context = new AnalysisBillingContext(
                userId: (string) $metadata->user_id,
                metadataId: (string) $metadata->id,
                fileId: (string) $metadata->file_id,
                folderId: $folderId,
            );

            $vision = AnalysisBillingContext::run(
                $context,
                fn (): array => $ai->analyzeVision($image, $this->hint),
            );

            $stages->markVisionOk($metadata->refresh(), $vision);

            AnalyzeAssetThinking::dispatch(
                (string) $metadata->id,
                $this->hint,
                $folderId,
            );
        } catch (Throwable $e) {
            report($e);
            $stages->markVisionFailed($metadata->refresh(), $e);

            if ($metadata->file->folder_id !== null) {
                $stages->completeFolderIfReady((string) $metadata->file->folder_id);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $metadata = AssetMetadata::query()->find($this->metadataId);
        if ($metadata === null) {
            return;
        }

        app(AnalysisStageService::class)->markVisionFailed($metadata, $e);

        $metadata->loadMissing('file');
        if ($metadata->file?->folder_id !== null) {
            app(AnalysisStageService::class)->completeFolderIfReady((string) $metadata->file->folder_id);
        }
    }
}
