<?php

namespace App\Domain\FileHosting\Console\Commands;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Abort pending MULTIPART upload sessions that are expired-past-grace OR inactive
 * (no new multipart data on S3 for N minutes), reclaiming orphaned S3 storage.
 *
 * Providers like Backblaze B2 do not expose an S3 lifecycle rule for incomplete
 * multipart uploads, so this scheduled sweep is the cleanup path (a cancelled or
 * abandoned upload otherwise keeps its uploaded parts on disk indefinitely).
 *
 * Activity source of truth = S3 ListParts `LastModified` (NOT DB `updated_at`):
 * even the slowest live connection keeps PUTting parts, so a session whose latest
 * part is older than `orphan_inactivity_minutes` — or that has zero parts — is
 * dead. A ListParts error means "unknown": skip rather than abort blindly.
 *
 * Multipart only: a single-PUT session has no dangling multipart on S3 and is
 * deliberately excluded — the inactivity arm would otherwise abort a healthy
 * slow single PUT mid-flight.
 */
class PruneStaleUploads extends Command
{
    protected $signature = 'file-hosting:prune-stale-uploads {--limit=500 : Max sessions to process per run}';

    protected $description = 'Abort expired or S3-inactive pending multipart upload sessions and reclaim orphaned multipart storage';

    public function handle(DirectUploadService $uploads, S3DirectUploadEngine $engine, ObjectKeyResolver $objectKeys): int
    {
        $grace = max(0, (int) config('file-hosting.stale_upload_grace_hours', 6));
        $cutoff = Carbon::now()->subHours($grace);
        $inactivityMins = max(1, (int) config('file-hosting.orphan_inactivity_minutes', 180));
        $inactivityCutoff = Carbon::now()->subMinutes($inactivityMins);
        $limit = max(1, (int) $this->option('limit'));

        // Arm 1: expired + past grace — abort outright (no S3 activity check needed).
        $expired = FileUpload::query()
            ->pending()
            ->where('strategy', FileUpload::STRATEGY_MULTIPART)
            ->where('expires_at', '<', $cutoff)
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        $aborted = 0;

        foreach ($expired as $upload) {
            // ACL-free, error-swallowing: still marks aborted so complete cannot promote.
            $uploads->forceAbortPending($upload);
            $aborted++;
        }

        // Arm 2: orphan-by-inactivity. Candidates are sessions whose DB row looks
        // idle past the inactivity window, but the decision is made on S3 truth:
        // abort only when ListParts shows no part newer than the cutoff (or no
        // parts at all). ListParts failure → skip (never abort on unknown state).
        $candidates = FileUpload::query()
            ->pending()
            ->where('strategy', FileUpload::STRATEGY_MULTIPART)
            ->where('updated_at', '<', $inactivityCutoff)
            ->whereNotNull('s3_upload_id')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($candidates as $upload) {
            if ($aborted >= $limit) {
                break;
            }

            if ($this->hasRecentS3Activity($engine, $objectKeys, $upload, $inactivityCutoff)) {
                continue;
            }

            $uploads->forceAbortPending($upload);
            $aborted++;
        }

        $this->info("Pruned stale uploads: {$aborted} (grace {$grace}h, inactivity {$inactivityMins}m)");

        return self::SUCCESS;
    }

    /**
     * True when S3 holds a part modified at/after the cutoff. Null (unknown /
     * ListParts error) is treated as "recent" so we never abort on unknown state.
     */
    private function hasRecentS3Activity(
        S3DirectUploadEngine $engine,
        ObjectKeyResolver $objectKeys,
        FileUpload $upload,
        Carbon $inactivityCutoff,
    ): bool {
        try {
            $latest = $engine->latestPartModifiedAt(
                (string) $upload->disk,
                $objectKeys->objectKey((string) $upload->user_id, (string) $upload->path),
                (string) $upload->s3_upload_id,
            );
        } catch (S3DirectUploadException) {
            return true; // Unknown → skip.
        }

        // No parts yet → only "active" if the session is younger than the window.
        if ($latest === null) {
            return $upload->created_at !== null && $upload->created_at->gte($inactivityCutoff);
        }

        return $latest->gte($inactivityCutoff);
    }
}
