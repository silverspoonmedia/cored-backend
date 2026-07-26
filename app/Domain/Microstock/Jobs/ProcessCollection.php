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
use Throwable;

/**
 * Orchestrates the AI pipeline for one Microstock collection (folder). Seeds a
 * pending metadata row per file directly inside the folder (non-recursive),
 * then hands off to a single AnalyzeCollectionBatch job that fans the files
 * out to the AI in as few multi-image requests as possible.
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
    ) {}

    public function handle(): void
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
            ->map(function (HostedFile $file): string {
                $metadata = AssetMetadata::query()->firstOrNew(['file_id' => $file->id]);
                $metadata->user_id = $file->user_id;
                $metadata->status = MetadataStatus::Pending;
                $metadata->save();

                return (string) $metadata->id;
            })
            ->all();

        if ($metadataIds === []) {
            $folder->markCollectionStatus(CollectionStatus::Failed);

            return;
        }

        AnalyzeCollectionBatch::dispatch($this->folderId, $metadataIds, $hint);
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Folder::query()->find($this->folderId)
            ?->markCollectionStatus(CollectionStatus::Failed);
    }
}
