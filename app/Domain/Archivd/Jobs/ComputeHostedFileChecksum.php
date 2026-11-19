<?php

namespace App\Domain\Archivd\Jobs;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Archivd\Support\ObjectStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Compute + store sha256 checksum for a promoted file, in the background.
 *
 * Why post-upload, server-side: resume upload was removed (its client-side
 * fingerprint was a major throughput regression), so the checksum is now computed
 * AFTER the file is fully uploaded, off the request path. Purpose = dedup + DMCA
 * hash blocklist — NOT resume identity. Runs after commit, so a failure here can
 * never block or roll back the upload.
 *
 * Idempotent: skips a file that already has a checksum (safe to re-dispatch).
 */
class ComputeHostedFileChecksum implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tolerate transient storage/network blips; never block the upload path.
     */
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $fileId,
    ) {}

    public function handle(ObjectStorage $storage, ObjectKeyResolver $objectKeys): void
    {
        $file = HostedFile::query()->find($this->fileId);

        if ($file === null) {
            return;
        }

        // Already computed (idempotent) — nothing to do.
        if (is_string($file->checksum) && $file->checksum !== '') {
            return;
        }

        $objectKey = $objectKeys->objectKey((string) $file->user_id, (string) $file->path);
        $stream = null;
        $checksum = null;

        try {
            $stream = $storage->readStream($objectKey, (string) $file->disk);

            if (is_resource($stream)) {
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);
                $checksum = hash_final($context);
            }
        } catch (Throwable) {
            // Object not (yet) readable — a transient storage blip; retry handles it.
            return;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($checksum === null) {
            return;
        }

        // Re-check before write: another worker may have raced and stored it.
        $file->refresh();

        if (is_string($file->checksum) && $file->checksum !== '') {
            return;
        }

        $file->forceFill(['checksum' => $checksum])->save();
    }

    /**
     * Swallow terminal failures: checksum is best-effort metadata, never worth
     * surfacing as an exception that could alarm on the upload path.
     */
    public function failed(Throwable $e): void
    {
        report($e);
    }
}
