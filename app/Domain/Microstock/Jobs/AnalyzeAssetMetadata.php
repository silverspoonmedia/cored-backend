<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Support\AnalysisBillingContext;
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

    /**
     * Long-running AI generation; vision + thinking can each run multi-step
     * reasoning for minutes. Must stay below queue retry_after (config/queue.php).
     */
    public int $timeout = 900;

    public function __construct(
        private readonly string $metadataId,
        private readonly ?string $hint = null,
    ) {}

    public function handle(
        AiMetadataService $ai,
        VisionImageResolver $images,
        AiBillingService $billing,
    ): void {
        $metadata = AssetMetadata::query()->with('file')->find($this->metadataId);

        if ($metadata === null || $metadata->file === null) {
            return;
        }

        // Idempotent: skip if already curated/finalized by a prior run.
        if ($metadata->status === MetadataStatus::Finalized) {
            return;
        }

        // Send a downscaled preview (not the full-size original) so the vision
        // gateway responds within its ~60s cutoff.
        $image = $images->resolve($metadata->file);

        $context = new AnalysisBillingContext(
            userId: (string) $metadata->user_id,
            metadataId: (string) $metadata->id,
            fileId: (string) $metadata->file_id,
            folderId: $metadata->file->folder_id !== null ? (string) $metadata->file->folder_id : null,
        );

        $result = AnalysisBillingContext::run($context, fn (): array => $ai->generate($image, $this->hint));

        $metadata->forceFill([
            'title' => $result['title'],
            'keywords' => $result['keywords'],
            'description' => $result['description'] ?? null,
            'category' => $result['category'] ?? null,
            'ai_raw' => [
                'vision' => $result['raw']['vision'] ?? null,
                'thinking' => $result['raw']['thinking'] ?? null,
                'stage_metrics' => $result['stage_metrics'] ?? [],
            ],
            'status' => MetadataStatus::Generated,
        ])->save();

        $billing->settleSuccessfulAnalysis($metadata->refresh());
    }

    public function failed(Throwable $e): void
    {
        report($e);

        AssetMetadata::query()->find($this->metadataId)
            ?->forceFill([
                'status' => MetadataStatus::Failed,
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();
    }
}
