<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
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
 * Vision-only chunk of a collection analysis. Persists per-asset vision output
 * and dispatches thinking jobs; collection review is deferred to the watchdog
 * or thinking job completion checker.
 */
class AnalyzeCollectionVisionBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    /**
     * @param  list<string>  $metadataIds
     */
    public function __construct(
        private readonly string $folderId,
        private readonly array $metadataIds,
        private readonly ?string $hint = null,
    ) {}

    public function handle(
        AiMetadataService $ai,
        VisionImageResolver $images,
        AnalysisStageService $stages,
    ): void {
        $folder = Folder::query()->find($this->folderId);
        if ($folder === null) {
            return;
        }

        $chunkSize = max(1, (int) config('microstock.ai.batch_chunk', 10));
        $currentIds = array_slice($this->metadataIds, 0, $chunkSize);
        $remainingIds = array_slice($this->metadataIds, $chunkSize);

        $metadata = AssetMetadata::query()
            ->with('file')
            ->whereIn('id', $currentIds)
            ->where('status', '!=', MetadataStatus::Finalized->value)
            ->get()
            ->filter(fn (AssetMetadata $m): bool => $m->file !== null)
            ->values();

        if ($metadata->isNotEmpty()) {
            $items = [];
            $byId = [];
            foreach ($metadata as $m) {
                try {
                    $items[] = ['id' => (string) $m->id, 'image' => $images->resolve($m->file)];
                    $byId[(string) $m->id] = $m;
                } catch (Throwable $e) {
                    report($e);
                    $stages->markVisionFailed($m, $e);
                    $stages->completeFolderIfReady($this->folderId);
                }
            }

            if ($items !== []) {
                $minBatch = max(1, (int) config('microstock.ai.batch_min_files', 4));

                if (count($items) < $minBatch) {
                    foreach ($items as $item) {
                        $this->runSingleVision($ai, $stages, $byId[$item['id']], $item['image']);
                    }
                } else {
                    $results = [];
                    try {
                        $results = $this->runBatchVision($ai, $folder, $items, $byId);
                    } catch (Throwable $e) {
                        report($e);
                    }

                    foreach ($items as $item) {
                        $m = $byId[$item['id']];
                        $row = $results[$item['id']] ?? null;

                        if ($row === null) {
                            $this->runSingleVision($ai, $stages, $m, $item['image']);

                            continue;
                        }

                        $this->persistVisionAndDispatchThinking($stages, $m, $this->normalizeBatchRow($row));
                    }
                }
            }
        }

        if ($remainingIds !== []) {
            static::dispatch($this->folderId, array_values($remainingIds), $this->hint);

            return;
        }

        $stages->completeFolderIfReady($this->folderId);
    }

    private function runSingleVision(
        AiMetadataService $ai,
        AnalysisStageService $stages,
        AssetMetadata $metadata,
        string $image,
    ): void {
        try {
            $stages->markVisionRunning($metadata->refresh());

            $context = new AnalysisBillingContext(
                userId: (string) $metadata->user_id,
                metadataId: (string) $metadata->id,
                fileId: (string) $metadata->file_id,
                folderId: $this->folderId,
            );

            $vision = AnalysisBillingContext::run(
                $context,
                fn (): array => $ai->analyzeVision($image, $this->hint),
            );

            $this->persistVisionAndDispatchThinking($stages, $metadata->refresh(), $vision);
        } catch (Throwable $e) {
            report($e);
            $stages->markVisionFailed($metadata->refresh(), $e);
            $stages->completeFolderIfReady($this->folderId);
        }
    }

    /**
     * @param  array<int, array{id: string, image: string}>  $items
     * @param  array<string, AssetMetadata>  $byId
     * @return array<string, array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}>
     */
    private function runBatchVision(AiMetadataService $ai, Folder $folder, array $items, array $byId): array
    {
        $first = reset($byId);
        $batchTargets = [];
        foreach ($items as $item) {
            $m = $byId[$item['id']];
            $batchTargets[] = [
                'metadata_id' => (string) $m->id,
                'file_id' => (string) $m->file_id,
            ];
        }

        $context = new AnalysisBillingContext(
            userId: (string) $first->user_id,
            folderId: (string) $folder->id,
            batchTargets: $batchTargets,
        );

        return AnalysisBillingContext::run($context, fn (): array => $ai->generateBatch($items, $this->hint));
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed, stage_metrics?: list<array<string, mixed>>}  $vision
     */
    private function persistVisionAndDispatchThinking(
        AnalysisStageService $stages,
        AssetMetadata $metadata,
        array $vision,
    ): void {
        $stages->markVisionOk($metadata, $vision);

        AnalyzeAssetThinking::dispatch(
            (string) $metadata->id,
            $this->hint,
            $this->folderId,
        );
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $row
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed, stage_metrics: list<array<string, mixed>>}
     */
    private function normalizeBatchRow(array $row): array
    {
        return [
            'title' => $row['title'],
            'keywords' => $row['keywords'],
            'description' => $row['description'] ?? '',
            'category' => $row['category'] ?? '',
            'raw' => $row['raw'] ?? null,
            'stage_metrics' => [],
        ];
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Folder::query()->find($this->folderId)
            ?->markCollectionStatus(CollectionStatus::Failed);
    }
}
