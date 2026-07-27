<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AssetResizeService;
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

    public function handle(AiMetadataService $ai, AssetResizeService $resizer): void
    {
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
                    $items[] = ['id' => (string) $m->id, 'image' => $resizer->previewDataUri($m->file)];
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
                            $single = $ai->generate($item['image'], $this->hint);
                            $this->markGenerated($m, $single);
                        } catch (Throwable $e) {
                            $this->markFailed($m, $e);
                        }
                    }
                } else {
                    $results = [];
                    try {
                        $results = $ai->generateBatch($items, $this->hint);
                    } catch (Throwable $e) {
                        report($e);
                    }

                    foreach ($items as $item) {
                        $m = $byId[$item['id']];
                        $row = $results[$item['id']] ?? null;

                        if ($row === null) {
                            // Batch skipped/failed this asset: fall back to single-file run.
                            try {
                                $single = $ai->generate($item['image'], $this->hint);
                                $this->markGenerated($m, $single);
                            } catch (Throwable $e) {
                                $this->markFailed($m, $e);
                            }

                            continue;
                        }

                        $this->curateAndMark($ai, $m, $row, $item['image']);
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
     * Run the thinking stage on a batch vision draft, then persist curated metadata.
     *
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $row
     */
    private function curateAndMark(AiMetadataService $ai, AssetMetadata $m, array $row, string $imageUrl): void
    {
        try {
            $curated = $ai->curateDraft(
                $row['title'],
                $row['keywords'],
                $this->hint,
                $row['description'] ?? '',
                $row['category'] ?? '',
                $imageUrl,
            );

            $this->markGenerated($m, [
                'title' => $curated['title'],
                'keywords' => $curated['keywords'],
                'description' => $curated['description'] ?? '',
                'category' => $curated['category'] ?? '',
                'raw' => [
                    'vision' => $row['raw'] ?? null,
                    'thinking' => $curated['raw'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            $this->markFailed($m, $e);
        }
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $row
     */
    private function markGenerated(AssetMetadata $m, array $row): void
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
