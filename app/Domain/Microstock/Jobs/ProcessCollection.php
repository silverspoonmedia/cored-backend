<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Orchestrates the AI pipeline for one Microstock collection (folder). Fans out
 * an AnalyzeAssetMetadata job per file directly inside the folder
 * (non-recursive), carrying the owner's collection hint, then flips the folder
 * to review once the batch settles (failed on error).
 */
class ProcessCollection implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly string $folderId,
    ) {}

    public function handle(): void
    {
        $folder = Folder::query()->find($this->folderId);

        if ($folder === null || $folder->collection_status !== CollectionStatus::Processing) {
            return;
        }

        $hint = $folder->collection_hint;

        $jobs = HostedFile::query()
            ->where('folder_id', $folder->id)
            ->orderBy('name')
            ->get()
            ->map(function (HostedFile $file) use ($hint): AnalyzeAssetMetadata {
                $metadata = AssetMetadata::query()->firstOrNew(['file_id' => $file->id]);
                $metadata->user_id = $file->user_id;
                $metadata->status = MetadataStatus::Pending;
                $metadata->save();

                return new AnalyzeAssetMetadata((string) $metadata->id, $hint);
            })
            ->all();

        if ($jobs === []) {
            $folder->markCollectionStatus(CollectionStatus::Failed);

            return;
        }

        $folderId = $this->folderId;

        Bus::batch($jobs)
            ->then(function () use ($folderId): void {
                Folder::query()->find($folderId)
                    ?->markCollectionStatus(CollectionStatus::Review);
            })
            ->catch(function () use ($folderId): void {
                Folder::query()->find($folderId)
                    ?->markCollectionStatus(CollectionStatus::Failed);
            })
            ->dispatch();
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Folder::query()->find($this->folderId)
            ?->markCollectionStatus(CollectionStatus::Failed);
    }
}
