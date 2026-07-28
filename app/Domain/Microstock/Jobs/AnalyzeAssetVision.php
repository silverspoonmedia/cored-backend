<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\AnalysisPipeline;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Single-asset vision stage. On success dispatches thinking on the paired lane queue.
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
        private readonly ?string $folderId = null,
        private readonly AnalysisPipeline $pipeline = AnalysisPipeline::Solo,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->pipeline->visionOverlapKey($this->folderId)))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public static function dispatchForCollection(string $metadataId, ?string $hint, string $folderId): void
    {
        static::dispatch($metadataId, $hint, $folderId, AnalysisPipeline::Collection)
            ->onQueue(AnalysisPipeline::Collection->visionQueue());
    }

    public static function dispatchForSolo(string $metadataId, ?string $hint, ?string $folderId = null): void
    {
        static::dispatch($metadataId, $hint, $folderId, AnalysisPipeline::Solo)
            ->onQueue(AnalysisPipeline::Solo->visionQueue());
    }

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

        $folderId = $this->folderId ?? ($metadata->file->folder_id !== null ? (string) $metadata->file->folder_id : null);

        $visionStatus = $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value;
        if ($visionStatus === AnalysisStageStatus::Ok->value) {
            $this->dispatchThinkingIfNeeded($folderId);

            return;
        }

        if ($visionStatus === AnalysisStageStatus::Running->value) {
            return;
        }

        $stages->markVisionRunning($metadata->refresh());

        try {
            $image = $images->resolve($metadata->file);

            $context = $this->billingContext($metadata, $folderId);

            $vision = AnalysisBillingContext::run(
                $context,
                fn (): array => $ai->analyzeVision($image, $this->hint),
            );

            $stages->markVisionOk($metadata->refresh(), $vision);

            $this->dispatchThinkingIfNeeded($folderId);
        } catch (Throwable $e) {
            report($e);
            $stages->markVisionFailed($metadata->refresh(), $e);

            if ($folderId !== null) {
                $stages->completeFolderIfReady($folderId);
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

        $folderId = $this->folderId;
        if ($folderId === null) {
            $metadata->loadMissing('file');
            $folderId = $metadata->file?->folder_id !== null ? (string) $metadata->file->folder_id : null;
        }

        if ($folderId !== null) {
            app(AnalysisStageService::class)->completeFolderIfReady($folderId);
        }
    }

    private function dispatchThinkingIfNeeded(?string $folderId): void
    {
        AnalyzeAssetThinking::dispatchForPipeline(
            $this->metadataId,
            $this->hint,
            $folderId,
            $this->pipeline,
        );
    }

    private function billingContext(AssetMetadata $metadata, ?string $folderId): AnalysisBillingContext
    {
        return new AnalysisBillingContext(
            billingUserId: (string) ($metadata->billing_user_id ?? $metadata->user_id),
            metadataId: (string) $metadata->id,
            fileId: (string) $metadata->file_id,
            folderId: $folderId,
            analysisRunId: $metadata->analysis_run_id,
        );
    }
}
