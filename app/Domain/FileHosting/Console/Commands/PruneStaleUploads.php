<?php

namespace App\Domain\FileHosting\Console\Commands;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Services\DirectUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Abort pending upload sessions that are expired-past-grace OR inactive (no part
 * activity for N minutes), reclaiming orphaned S3 multipart storage.
 *
 * Providers like Backblaze B2 do not expose an S3 lifecycle rule for incomplete
 * multipart uploads, so this scheduled sweep is the cleanup path (a cancelled or
 * abandoned upload otherwise keeps its uploaded parts on disk indefinitely). The
 * inactivity arm additionally frees max_concurrent_uploads slots held by sessions
 * orphaned right after init (before their 24h TTL elapses).
 */
class PruneStaleUploads extends Command
{
    protected $signature = 'file-hosting:prune-stale-uploads {--limit=500 : Max sessions to process per run}';

    protected $description = 'Abort expired or inactive pending upload sessions and reclaim orphaned multipart storage';

    public function handle(DirectUploadService $uploads): int
    {
        $grace = max(0, (int) config('file-hosting.stale_upload_grace_hours', 6));
        $cutoff = Carbon::now()->subHours($grace);
        $inactivityMins = max(1, (int) config('file-hosting.orphan_inactivity_minutes', 60));
        $inactivityCutoff = Carbon::now()->subMinutes($inactivityMins);
        $limit = max(1, (int) $this->option('limit'));

        // Two reclamation paths, unioned:
        //  (1) expired + past grace — the original rule.
        //  (2) orphan-by-inactivity: no part activity for N minutes, even if not yet
        //      expired. Catches cancel/tab-close-right-after-init that would otherwise
        //      hold a max_concurrent_uploads slot for the full 24h TTL. A live upload
        //      refreshes updated_at on every landed part (recordParts/complete), so
        //      only genuinely-dead sessions trip this.
        $stale = FileUpload::query()
            ->pending()
            ->where(function ($q) use ($cutoff, $inactivityCutoff): void {
                $q->where('expires_at', '<', $cutoff)
                    ->orWhere('updated_at', '<', $inactivityCutoff);
            })
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        $aborted = 0;

        foreach ($stale as $upload) {
            // ACL-free, error-swallowing: still marks aborted so complete cannot promote.
            $uploads->forceAbortPending($upload);
            $aborted++;
        }

        $this->info("Pruned stale uploads: {$aborted} (grace {$grace}h, inactivity {$inactivityMins}m, cutoff {$cutoff->toDateTimeString()})");

        return self::SUCCESS;
    }
}
