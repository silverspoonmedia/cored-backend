<?php

namespace App\Domain\Microstock\Services;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\ProcessCollection;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Support\MicrostockRealtime;
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
        private readonly AiBillingService $billing,
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
    public function lock(User $actor, string $folderId, ?string $hint, ?string $billingMode = null): Folder
    {
        $folder = $this->resolveCollection($actor, $folderId);

        $hint = $hint !== null ? trim($hint) : '';
        $billingMode = $this->resolveBillingMode($billingMode);

        $status = $folder->collection_status ?? CollectionStatus::Draft;
        if (! $status->canBeLocked()) {
            throw new MicrostockException(__('api/microstock.collection_already_locked'), 422);
        }

        $folder->forceFill([
            'collection_status' => CollectionStatus::Locked,
            'collection_hint' => $hint === '' ? null : $hint,
            'collection_locked_at' => now(),
            'collection_billing_mode' => $billingMode,
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

        $fileCount = $this->filesIn($folder)->count();
        $billingMode = $folder->collection_billing_mode;

        $plan = $this->billing->planAnalyze($actor, $fileCount, $billingMode);

        $folder->markCollectionStatus(CollectionStatus::Processing);

        ProcessCollection::dispatch(
            (string) $folder->id,
            (string) $actor->id,
            (string) Str::uuid(),
            $plan->dailySlots,
            $billingMode,
        );

        $folder = $folder->refresh();

        app(MicrostockRealtime::class)
            ->collectionProgress($folder, force: true);

        return $folder;
    }

    /**
     * Resolve and validate the billing mode chosen at lock time.
     * When daily free is disabled, all analyses are paid.
     * When enabled, the user must explicitly choose free or paid.
     */
    private function resolveBillingMode(?string $billingMode): string
    {
        $dailyFreeEnabled = app(AiDailyFreeService::class)->isEnabled();

        if (! $dailyFreeEnabled) {
            return 'paid';
        }

        if (! in_array($billingMode, ['free', 'paid'], true)) {
            throw new MicrostockException(
                __('api/microstock.billing_mode_required'),
                422,
            );
        }

        return $billingMode;
    }

    /**
     * Progress snapshot for the status badge: counts of files by metadata state.
     *
     * @return array{status: string, hint: string|null, total: int, processed: int, finalized: int}
     */
    public function status(User $actor, string $folderId): array
    {
        $folder = $this->resolveCollection($actor, $folderId);

        return $this->statusSnapshot($folder);
    }

    /**
     * Progress snapshot without auth — for websocket broadcasts from queue workers.
     *
     * @return array{status: string, phase: string, billing_tier: string|null, hint: string|null, csv_path: string|null, total: int, processed: int, finalized: int, failed: int}
     */
    public function statusSnapshot(Folder $folder): array
    {
        $fileIds = $this->filesIn($folder)->pluck('id');
        $total = $fileIds->count();

        $processed = 0;
        $finalized = 0;
        $failed = 0;
        if ($total > 0) {
            $processed = AssetMetadata::query()
                ->whereIn('file_id', $fileIds)
                ->whereIn('status', [MetadataStatus::Generated, MetadataStatus::Finalized])
                ->count();
            $finalized = AssetMetadata::query()
                ->whereIn('file_id', $fileIds)
                ->where('status', MetadataStatus::Finalized)
                ->count();
            $failed = AssetMetadata::query()
                ->whereIn('file_id', $fileIds)
                ->where('status', MetadataStatus::Failed)
                ->count();
        }

        $collectionStatus = $folder->collection_status ?? CollectionStatus::Draft;

        return [
            'status' => $collectionStatus->value,
            'phase' => $this->collectionPhase($folder, $collectionStatus),
            'billing_tier' => $this->billingTierForCollection($fileIds, $collectionStatus),
            'hint' => $folder->collection_hint,
            'csv_path' => $folder->collection_csv_path,
            'total' => $total,
            'processed' => $processed,
            'finalized' => $finalized,
            'failed' => $failed,
        ];
    }

    private function billingTierForCollection(\Illuminate\Support\Collection $fileIds, CollectionStatus $status): ?string
    {
        if ($status !== CollectionStatus::Processing || $fileIds->isEmpty()) {
            return null;
        }

        $sources = AssetMetadata::query()
            ->whereIn('file_id', $fileIds)
            ->where('status', MetadataStatus::Pending)
            ->pluck('billing_source')
            ->filter()
            ->unique()
            ->values();

        if ($sources->isEmpty()) {
            return null;
        }

        $hasDaily = $sources->contains(BillingSource::DailyFree->value);
        $hasPrepaid = $sources->contains(BillingSource::Prepaid->value);

        if ($hasDaily && $hasPrepaid) {
            return 'mixed';
        }

        if ($hasDaily) {
            return 'free';
        }

        return 'paid';
    }

    /**
     * Derived analysis phase for a collection in flight. Presentational only —
     * never persisted. `processing` maps to analyzing/degraded by how long the
     * collection has been locked; every other status maps to itself.
     */
    private function collectionPhase(Folder $folder, CollectionStatus $status): string
    {
        if ($status !== CollectionStatus::Processing) {
            return $status->value;
        }

        $startedAt = $folder->collection_locked_at ?? $folder->updated_at;

        return $startedAt !== null ? self::phaseFor($startedAt) : 'analyzing';
    }

    /**
     * Map an analysis start timestamp to its adaptive phase by elapsed time.
     *   elapsed < normal_after  -> analyzing (AI is processing)
     *   normal..grace           -> degraded (still waiting; UI shows a warning)
     * Past grace the watchdog flips the row to failed, so this method only ever
     * returns analyzing|degraded.
     */
    public static function phaseFor(\DateTimeInterface $startedAt): string
    {
        $normal = (int) setting_config('microstock.ai.phase.normal_after', 600);
        $elapsed = now()->getTimestamp() - $startedAt->getTimestamp();

        return $elapsed < $normal ? 'analyzing' : 'degraded';
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
        Storage::disk(setting_config('microstock.csv_disk', 'local'))
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
        if ($path === null || ! Storage::disk(setting_config('microstock.csv_disk', 'local'))->exists($path)) {
            throw new MicrostockException(__('api/microstock.collection_csv_missing'), 404);
        }

        $contents = Storage::disk(setting_config('microstock.csv_disk', 'local'))->get($path);

        return [
            'contents' => $contents,
            'name' => Str::slug($folder->name).'.csv',
        ];
    }
}
