<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\ObjectStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * After subscription grace ends: keep oldest files until free-tier limit.
 * Excess: hard-delete object storage + soft-delete DB row (quota sum ignores trash).
 *
 * Sort: created_at ASC, id ASC. Accumulate; files that would exceed free limit are removed.
 */
class EnforceFreeTierQuotaService
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly QuotaService $quota,
    ) {}

    /**
     * @return array{deleted: int, freed_bytes: int, retained_bytes: int, limit_bytes: int}
     */
    public function enforce(string $userId): array
    {
        $limit = $this->quota->freeTierQuotaBytes();

        if ($limit <= 0) {
            return ['deleted' => 0, 'freed_bytes' => 0, 'retained_bytes' => 0, 'limit_bytes' => $limit];
        }

        $used = (int) HostedFile::query()->where('user_id', $userId)->sum('size');

        if ($used <= $limit) {
            return [
                'deleted' => 0,
                'freed_bytes' => 0,
                'retained_bytes' => $used,
                'limit_bytes' => $limit,
            ];
        }

        $files = HostedFile::query()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'size', 'disk', 'path', 'created_at']);

        $running = 0;
        $toDelete = [];

        foreach ($files as $file) {
            $size = (int) $file->size;

            if ($running + $size <= $limit) {
                $running += $size;

                continue;
            }

            $toDelete[] = $file;
        }

        $freed = 0;

        foreach ($toDelete as $file) {
            $this->purgeFile($file);
            $freed += (int) $file->size;
        }

        Log::info('file_hosting.enforce_free_tier', [
            'user_id' => $userId,
            'limit_bytes' => $limit,
            'deleted' => count($toDelete),
            'freed_bytes' => $freed,
            'retained_bytes' => $running,
        ]);

        return [
            'deleted' => count($toDelete),
            'freed_bytes' => $freed,
            'retained_bytes' => $running,
            'limit_bytes' => $limit,
        ];
    }

    private function purgeFile(HostedFile $file): void
    {
        DB::transaction(function () use ($file): void {
            $this->storage->deleteMany([['disk' => $file->disk, 'path' => $file->path]]);

            if (! $file->trashed()) {
                $file->delete();
            }
        });
    }
}
