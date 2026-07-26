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
     * Whole-collection generation can take a while for many images. Keep below
     * queue retry_after (config/queue.php) so a slow run is not duplicated.
     */
    public int $timeout = 300;

    /**
     * @param  list<string>  $metadataIds
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

        $metadata = AssetMetadata::query()
            ->with('file')
            ->whereIn('id', $this->metadataIds)
            ->where('status', '!=', MetadataStatus::Finalized->value)
            ->get()
            ->filter(fn (AssetMetadata $m): bool => $m->file !== null)
            ->values();

        if ($metadata->isEmpty()) {
            $folder->markCollectionStatus(CollectionStatus::Review);

            return;
        }

        $chunkSize = max(1, (int) config('microstock.ai.batch_chunk', 10));

        foreach ($metadata->chunk($chunkSize) as $chunk) {
            $items = [];
            $byId = [];
            foreach ($chunk as $m) {
                try {
                    $items[] = ['id' => (string) $m->id, 'image' => $resizer->previewDataUri($m->file)];
                    $byId[(string) $m->id] = $m;
                } catch (Throwable $e) {
                    $this->markFailed($m, $e);
                }
            }

            if ($items === []) {
                continue;
            }

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
                    // Batch skipped/failed this asset: fall back to a single-file run.
                    try {
                        $single = $ai->generate($item['image'], $this->hint);
                        $this->markGenerated($m, $single);
                    } catch (Throwable $e) {
                        $this->markFailed($m, $e);
                    }

                    continue;
                }

                $this->markGenerated($m, $row);
            }
        }

        $folder->markCollectionStatus(CollectionStatus::Review);
    }

    /**
     * @param  array{title: string, keywords: list<string>, raw: mixed}  $row
     */
    private function markGenerated(AssetMetadata $m, array $row): void
    {
        $m->forceFill([
            'title' => $row['title'],
            'keywords' => $row['keywords'],
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
