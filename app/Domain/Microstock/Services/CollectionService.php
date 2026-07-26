<?php

namespace App\Domain\Microstock\Services;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\ProcessCollection;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Microstock collection lifecycle: a File Hosting folder inside a Microstock
 * workspace is treated as one "collection". Locking it (with a user hint)
 * freezes uploads and kicks off the per-file AI pipeline; curation then CSV
 * export happen per collection.
 */
class CollectionService
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly CsvExportService $csv,
    ) {}

    /**
     * Resolve a folder the actor may manage, asserting it belongs to a
     * Microstock system workspace.
     */
    public function resolveCollection(User $actor, string $folderId): Folder
    {
        $folder = Folder::query()->find($folderId);

        if ($folder === null) {
            throw new MicrostockException(__('api/microstock.collection_not_found'), 404);
        }

        $workspace = $folder->workspace;

        if ($workspace === null || $workspace->kind !== WorkspaceKind::Microstock) {
            throw new MicrostockException(__('api/microstock.collection_not_found'), 404);
        }

        $this->workspaceAccess->assertCanMutate($workspace, $actor);

        return $folder;
    }

    /**
     * Lock the collection and store the owner's description (AI hint). Allowed
     * from the open states (draft/failed); re-locking re-runs the flow.
     */
    public function lock(User $actor, string $folderId, string $hint): Folder
    {
        $folder = $this->resolveCollection($actor, $folderId);

        $hint = trim($hint);
        if ($hint === '') {
            throw new MicrostockException(__('api/microstock.collection_hint_required'), 422);
        }

        $status = $folder->collection_status ?? CollectionStatus::Draft;
        if (! $status->canBeLocked()) {
            throw new MicrostockException(__('api/microstock.collection_already_locked'), 422);
        }

        $folder->forceFill([
            'collection_status' => CollectionStatus::Locked,
            'collection_hint' => $hint,
            'collection_locked_at' => now(),
        ])->save();

        return $folder->refresh();
    }

    /**
     * Start the AI pipeline for every file directly inside the collection
     * (non-recursive). Only valid from the locked state.
     */
    public function analyze(User $actor, string $folderId): Folder
    {
        $folder = $this->resolveCollection($actor, $folderId);

        if ($folder->collection_status !== CollectionStatus::Locked) {
            throw new MicrostockException(__('api/microstock.collection_not_locked'), 422);
        }

        if ($this->filesIn($folder)->isEmpty()) {
            throw new MicrostockException(__('api/microstock.collection_empty'), 422);
        }

        $folder->markCollectionStatus(CollectionStatus::Processing);

        ProcessCollection::dispatch((string) $folder->id);

        return $folder->refresh();
    }

    /**
     * Progress snapshot for the status badge: counts of files by metadata state.
     *
     * @return array{status: string, hint: string|null, total: int, processed: int, finalized: int}
     */
    public function status(User $actor, string $folderId): array
    {
        $folder = $this->resolveCollection($actor, $folderId);

        $fileIds = $this->filesIn($folder)->pluck('id');
        $total = $fileIds->count();

        $processed = 0;
        $finalized = 0;
        if ($total > 0) {
            $processed = AssetMetadata::query()
                ->whereIn('file_id', $fileIds)
                ->whereIn('status', [MetadataStatus::Generated, MetadataStatus::Finalized])
                ->count();
            $finalized = AssetMetadata::query()
                ->whereIn('file_id', $fileIds)
                ->where('status', MetadataStatus::Finalized)
                ->count();
        }

        return [
            'status' => ($folder->collection_status ?? CollectionStatus::Draft)->value,
            'hint' => $folder->collection_hint,
            'csv_path' => $folder->collection_csv_path,
            'total' => $total,
            'processed' => $processed,
            'finalized' => $finalized,
        ];
    }

    /**
     * Files directly inside the collection folder (non-recursive by design —
     * sub-collections are processed separately).
     *
     * @return Collection<int, HostedFile>
     */
    public function filesIn(Folder $folder): Collection
    {
        return HostedFile::query()
            ->where('folder_id', $folder->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Approve the curated collection: render a CSV of its finalized metadata,
     * persist it to disk, and mark the collection exported.
     *
     * @return array{path: string, count: int}
     */
    public function export(User $actor, string $folderId): array
    {
        $folder = $this->resolveCollection($actor, $folderId);

        if ($folder->collection_status !== CollectionStatus::Review) {
            throw new MicrostockException(__('api/microstock.collection_not_ready'), 422);
        }

        $built = $this->csv->buildForCollection($folder);

        if ($built['count'] === 0) {
            throw new MicrostockException(__('api/microstock.collection_nothing_finalized'), 422);
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($built['rows'] as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        $path = 'microstock/collections/'.$folder->id.'.csv';
        Storage::disk(config('microstock.csv_disk', 'local'))
            ->put($path, $contents === false ? '' : $contents);

        $folder->forceFill([
            'collection_csv_path' => $path,
            'collection_status' => CollectionStatus::Exported,
        ])->save();

        return ['path' => $path, 'count' => $built['count']];
    }

    /**
     * Absolute download contents for a previously exported collection CSV.
     *
     * @return array{contents: string, name: string}
     */
    public function downloadCsv(User $actor, string $folderId): array
    {
        $folder = $this->resolveCollection($actor, $folderId);

        $path = $folder->collection_csv_path;
        if ($path === null || ! Storage::disk(config('microstock.csv_disk', 'local'))->exists($path)) {
            throw new MicrostockException(__('api/microstock.collection_csv_missing'), 404);
        }

        $contents = Storage::disk(config('microstock.csv_disk', 'local'))->get($path);

        return [
            'contents' => $contents,
            'name' => Str::slug($folder->name).'.csv',
        ];
    }
}
