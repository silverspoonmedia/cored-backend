<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Orchestrates the AI pipeline for one Microstock collection (folder). Seeds a
 * pending metadata row per file directly inside the folder (non-recursive),
 * then dispatches per-asset vision jobs on the collection vision lane.
 */
class ProcessCollection implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private readonly string $folderId,
        private readonly string $billingUserId,
        private readonly string $analysisRunId,
        private readonly int $dailySlots = 0,
    ) {}

    public function handle(AnalysisStageService $stages): void
    {
        $folder = Folder::query()->find($this->folderId);

        if ($folder === null || $folder->collection_status !== CollectionStatus::Processing) {
            return;
        }

        $hint = $folder->collection_hint;

        $metadataIds = HostedFile::query()
            ->where('folder_id', $folder->id)
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (HostedFile $file, int $index) use ($stages): string {
                $metadata = AssetMetadata::query()->firstOrNew(['file_id' => $file->id]);
                $metadata->user_id = $file->user_id;
                $source = $index < $this->dailySlots
                    ? BillingSource::DailyFree
                    : BillingSource::Prepaid;
                $stages->initializeStages(
                    $metadata,
                    $this->billingUserId,
                    $this->analysisRunId,
                    $source,
                );

                return (string) $metadata->id;
            })
            ->all();

        if ($metadataIds === []) {
            $folder->markCollectionStatus(CollectionStatus::Failed);

            return;
        }

        foreach ($metadataIds as $metadataId) {
            AnalyzeAssetVision::dispatchForCollection($metadataId, $hint, $this->folderId);
        }

        AnalysisWatchdog::dispatch();
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Folder::query()->find($this->folderId)
            ?->markCollectionStatus(CollectionStatus::Failed);
    }
}
