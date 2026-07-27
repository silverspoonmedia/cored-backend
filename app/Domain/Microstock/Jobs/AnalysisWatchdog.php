<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Single authority that declares an AI analysis failed. The vision/thinking
 * stages run multi-step reasoning with no predictable duration, so failure is
 * not a per-request hard timeout but a phase deadline measured from when the
 * metadata row was queued (created_at). This job runs periodically while any
 * collection is in `processing`:
 *   - any `pending` metadata past the grace period -> failed (covers both a
 *     hung AI call and a zombie row whose worker died, e.g. Windows
 *     `queue:work` exit);
 *   - a `processing` folder whose metadata are all terminal -> failed.
 *
 * grace_after MUST stay below the queue retry_after (DB_QUEUE_RETRY_AFTER) so
 * this job declares failure before the queue re-dispatches the still-running
 * batch job.
 */
class AnalysisWatchdog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $backoff = 30;

    public function handle(): void
    {
        $this->failExpiredPending();

        $this->failCompletedProcessingFolders();

        // Stay armed only while work is in flight; otherwise stop.
        if ($this->hasProcessingCollections()) {
            static::dispatch()->delay(now()->addSeconds(60));
        }
    }

    /**
     * Flip pending metadata whose grace period has elapsed to failed. Also
     * reaps zombie rows (dispatched but the worker never picked them up or
     * died mid-run) — same rule, since a truly-pending row past grace cannot
     * still be legitimately processing.
     */
    private function failExpiredPending(): void
    {
        $grace = (int) config('microstock.ai.phase.grace_after', 1500);

        AssetMetadata::query()
            ->where('status', MetadataStatus::Pending->value)
            ->where('created_at', '<', now()->subSeconds($grace))
            ->each(function (AssetMetadata $m): void {
                $m->forceFill([
                    'status' => MetadataStatus::Failed,
                    'error' => 'Analysis exceeded the grace period',
                ])->save();
            });
    }

    /**
     * A `processing` folder whose every metadata row is terminal (generated /
     * finalized / failed) was never flipped to review — meaning the batch chain
     * died before the final invocation. Mark the folder failed so the user can
     * re-trigger.
     */
    private function failCompletedProcessingFolders(): void
    {
        Folder::query()
            ->where('collection_status', CollectionStatus::Processing->value)
            ->each(function (Folder $folder): void {
                $pending = AssetMetadata::query()
                    ->whereHas('file', fn ($q) => $q->where('folder_id', $folder->id))
                    ->where('status', MetadataStatus::Pending->value)
                    ->exists();

                if ($pending) {
                    return;
                }

                // No pending rows left but the folder is still processing: the
                // batch chain never reached its terminal `markCollectionStatus
                // (Review)`. Treat as failed unless there is at least one
                // generated/finalized row (an empty folder is a different
                // failure path handled by ProcessCollection).
                $hasTerminal = AssetMetadata::query()
                    ->whereHas('file', fn ($q) => $q->where('folder_id', $folder->id))
                    ->whereIn('status', [
                        MetadataStatus::Generated->value,
                        MetadataStatus::Finalized->value,
                    ])
                    ->exists();

                if ($hasTerminal) {
                    // Some assets succeeded — let the user curate what is there.
                    $folder->markCollectionStatus(CollectionStatus::Review);
                } else {
                    $folder->markCollectionStatus(CollectionStatus::Failed);
                }
            });
    }

    private function hasProcessingCollections(): bool
    {
        return Folder::query()
            ->where('collection_status', CollectionStatus::Processing->value)
            ->exists();
    }
}
