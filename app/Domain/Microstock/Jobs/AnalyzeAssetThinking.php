<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\AnalysisPipeline;
use App\Domain\Microstock\Enums\BillingSource;
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
 * Thinking stage for one asset. Requires a successful vision draft in ai_vision.
 */
class AnalyzeAssetThinking implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 900;

    public function __construct(
        private readonly string $metadataId,
        private readonly ?string $hint = null,
        private readonly ?string $folderId = null,
        private readonly AnalysisPipeline $pipeline = AnalysisPipeline::Collection,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->pipeline->thinkingOverlapKey($this->folderId)))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
            (new WithoutOverlapping($this->pipeline->thinkingMetadataOverlapKey($this->metadataId)))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public static function dispatchForPipeline(
        string $metadataId,
        ?string $hint,
        ?string $folderId,
        AnalysisPipeline $pipeline,
    ): void {
        $metadata = AssetMetadata::query()->find($metadataId);
        $freeTier = BillingSource::tryFrom((string) ($metadata?->billing_source ?? '')) === BillingSource::DailyFree;

        static::dispatch($metadataId, $hint, $folderId, $pipeline)
            ->onQueue($pipeline->thinkingQueue($freeTier));
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

        if ($metadata->status === MetadataStatus::Generated) {
            return;
        }

        $visionStatus = $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value;
        if ($visionStatus !== AnalysisStageStatus::Ok->value) {
            return;
        }

        $draft = $metadata->ai_vision['output'] ?? null;
        if (! is_array($draft)) {
            $stages->markThinkingFailed($metadata, 'Vision output missing');
            $this->completeFolder($stages);

            return;
        }

        if (! $stages->tryClaimThinkingRunning($metadata->refresh())) {
            return;
        }

        try {
            $image = $images->resolve($metadata->file);

            $folderId = $this->folderId ?? ($metadata->file->folder_id !== null ? (string) $metadata->file->folder_id : null);

            $source = BillingSource::tryFrom((string) ($metadata->billing_source ?? ''))
                ?? BillingSource::Prepaid;

            $context = new AnalysisBillingContext(
                billingUserId: (string) ($metadata->billing_user_id ?? $metadata->user_id),
                metadataId: (string) $metadata->id,
                fileId: (string) $metadata->file_id,
                folderId: $folderId,
                analysisRunId: $metadata->analysis_run_id,
                billingSource: $source,
            );

            $curated = AnalysisBillingContext::run(
                $context,
                fn (): array => $ai->curateDraft(
                    (string) $draft['title'],
                    is_array($draft['keywords'] ?? null) ? $draft['keywords'] : [],
                    $this->hint,
                    (string) ($draft['description'] ?? ''),
                    (string) ($draft['category'] ?? ''),
                    $image,
                ),
            );

            if (($curated['raw'] ?? null) === null) {
                $stages->markThinkingFailed($metadata->refresh(), 'Thinking stage returned no curated output');
                $this->completeFolder($stages);

                return;
            }

            $stages->composeResultFromThinking($metadata->refresh(), $curated);
            $this->completeFolder($stages);
        } catch (Throwable $e) {
            report($e);
            $stages->markThinkingFailed($metadata->refresh(), $e);
            $this->completeFolder($stages);
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $metadata = AssetMetadata::query()->find($this->metadataId);
        if ($metadata === null) {
            return;
        }

        $stages = app(AnalysisStageService::class);
        $stages->markThinkingFailed($metadata, $e);
        $this->completeFolder($stages);
    }

    private function completeFolder(AnalysisStageService $stages): void
    {
        if ($this->folderId !== null) {
            $stages->completeFolderIfReady($this->folderId);
        }
    }
}
