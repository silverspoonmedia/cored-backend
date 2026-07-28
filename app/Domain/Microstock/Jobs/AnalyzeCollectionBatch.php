<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Analyzes a whole microstock collection in as few AI requests as possible.
 * Files are chunked and sent to the provider in one multi-image request per
 * chunk; any asset the batch omits falls back to a single-file analysis.
 */
class AnalyzeCollectionBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    /**
     * One chunk per invocation; each chunk triggers one (potentially slow)
     * multi-image AI request plus per-asset single-file fallbacks. Chained
     * dispatch keeps the whole collection long-running without ever exceeding
     * retry_after, so the job is never duplicated mid-run.
     */
    public int $timeout = 600;

    /**
     * @param  list<string>  $metadataIds  Remaining metadata ids to process.
     */
    public function __construct(
        private readonly string $folderId,
        private readonly array $metadataIds,
        private readonly ?string $hint = null,
    ) {}

    public function handle(
        AiMetadataService $ai,
        VisionImageResolver $images,
        AiBillingService $billing,
    ): void {
        $folder = Folder::query()->find($this->folderId);
        if ($folder === null) {
            return;
        }

        $chunkSize = max(1, (int) config('microstock.ai.batch_chunk', 10));

        // This invocation handles only the first chunk; the rest is chained.
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
                    $this->markFailed($m, $e);
                }
            }

            if ($items !== []) {
                $minBatch = max(1, (int) config('microstock.ai.batch_min_files', 4));

                if (count($items) < $minBatch) {
                    foreach ($items as $item) {
                        $m = $byId[$item['id']];
                        try {
                            $single = $this->runSingleAnalysis($ai, $m, $item['image']);
                            $this->markGenerated($m, $this->normalizeGenerateResult($single), $billing);
                        } catch (Throwable $e) {
                            $this->markFailed($m, $e);
                        }
                    }
                } else {
                    $results = [];
                    try {
                        $results = $this->runBatchAnalysis($ai, $folder, $items, $byId);
                    } catch (Throwable $e) {
                        report($e);
                    }

                    foreach ($items as $item) {
                        $m = $byId[$item['id']];
                        $row = $results[$item['id']] ?? null;

                        if ($row === null) {
                            // Batch skipped/failed this asset: fall back to single-file run.
                            try {
                                $single = $this->runSingleAnalysis($ai, $m, $item['image']);
                                $this->markGenerated($m, $this->normalizeGenerateResult($single), $billing);
                            } catch (Throwable $e) {
                                $this->markFailed($m, $e);
                            }

                            continue;
                        }

                        $this->curateAndMark($ai, $m, $row, $item['image'], $billing);
                    }
                }
            }
        }

        if ($remainingIds !== []) {
            // Chain the next chunk; Review is marked by the final invocation.
            static::dispatch($this->folderId, array_values($remainingIds), $this->hint);

            return;
        }

        $folder->markCollectionStatus(CollectionStatus::Review);
    }

    /**
     * @param  array<int, array{id: string, image: string}>  $items
     * @param  array<string, AssetMetadata>  $byId
     * @return array<string, array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}>
     */
    private function runBatchAnalysis(AiMetadataService $ai, Folder $folder, array $items, array $byId): array
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
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function runSingleAnalysis(AiMetadataService $ai, AssetMetadata $m, string $image): array
    {
        $context = new AnalysisBillingContext(
            userId: (string) $m->user_id,
            metadataId: (string) $m->id,
            fileId: (string) $m->file_id,
            folderId: $this->folderId,
        );

        return AnalysisBillingContext::run($context, fn (): array => $ai->generate($image, $this->hint));
    }

    /**
     * Run the thinking stage on a batch vision draft, then persist curated metadata.
     *
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $row
     */
    private function curateAndMark(
        AiMetadataService $ai,
        AssetMetadata $m,
        array $row,
        string $imageUrl,
        AiBillingService $billing,
    ): void {
        try {
            $context = new AnalysisBillingContext(
                userId: (string) $m->user_id,
                metadataId: (string) $m->id,
                fileId: (string) $m->file_id,
                folderId: $this->folderId,
            );

            $curated = AnalysisBillingContext::run(
                $context,
                fn (): array => $ai->curateDraft(
                    $row['title'],
                    $row['keywords'],
                    $this->hint,
                    $row['description'] ?? '',
                    $row['category'] ?? '',
                    $imageUrl,
                ),
            );

            $this->markGenerated($m, [
                'title' => $curated['title'],
                'keywords' => $curated['keywords'],
                'description' => $curated['description'] ?? '',
                'category' => $curated['category'] ?? '',
                'raw' => [
                    'vision' => $row['raw'] ?? null,
                    'thinking' => $curated['raw'] ?? null,
                    'stage_metrics' => $curated['stage_metrics'] ?? [],
                ],
            ], $billing);
        } catch (Throwable $e) {
            $this->markFailed($m, $e);
        }
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: array<string, mixed>, stage_metrics?: list<array<string, mixed>>}  $result
     * @return array{title: string, keywords: list<string>, description?: string, category?: string, raw: array{vision: mixed, thinking: mixed, stage_metrics: list<array<string, mixed>>}}
     */
    private function normalizeGenerateResult(array $result): array
    {
        return [
            'title' => $result['title'],
            'keywords' => $result['keywords'],
            'description' => $result['description'] ?? '',
            'category' => $result['category'] ?? '',
            'raw' => [
                'vision' => $result['raw']['vision'] ?? null,
                'thinking' => $result['raw']['thinking'] ?? null,
                'stage_metrics' => $result['stage_metrics'] ?? [],
            ],
        ];
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $row
     */
    private function markGenerated(AssetMetadata $m, array $row, AiBillingService $billing): void
    {
        $m->forceFill([
            'title' => $row['title'],
            'keywords' => $row['keywords'],
            'description' => $row['description'] ?? null,
            'category' => $row['category'] ?? null,
            'ai_raw' => $row['raw'] ?? null,
            'status' => MetadataStatus::Generated,
            'error' => null,
        ])->save();

        $billing->settleSuccessfulAnalysis($m->refresh());
    }

    private function markFailed(AssetMetadata $m, Throwable $e): void
    {
        report($e);

        $m->forceFill([
            'status' => MetadataStatus::Failed,
            'error' => mb_substr($e->getMessage(), 0, 1000),
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Folder::query()->find($this->folderId)
            ?->markCollectionStatus(CollectionStatus::Failed);
    }
}
