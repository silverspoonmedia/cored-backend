<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Models\ArchivdHashBlocklist;
use App\Domain\Archivd\Models\HostedFile;

/**
 * sha256 hash blocklist (DMCA / compliance).
 *
 * A file whose checksum appears here must never be publicly shared: enabling
 * anyone_with_link is rejected and guest public access denies as 404. Queries
 * hit a 64-char unique index — no in-memory memo, so the service stays correct
 * on long-lived containers (Octane) and when the table changes mid-request.
 */
class HashBlocklistService
{
    public function isBlocked(?string $sha256): bool
    {
        if ($sha256 === null || $sha256 === '') {
            return false;
        }

        return ArchivdHashBlocklist::query()
            ->where('sha256', strtolower($sha256))
            ->exists();
    }

    public function isFileBlocked(HostedFile $file): bool
    {
        return $this->isBlocked(is_string($file->checksum) ? $file->checksum : null);
    }

    public function block(string $sha256, ?string $reason = null, ?string $createdBy = null): ArchivdHashBlocklist
    {
        $sha256 = strtolower(trim($sha256));

        return ArchivdHashBlocklist::query()->firstOrCreate(
            ['sha256' => $sha256],
            ['reason' => $reason, 'created_by' => $createdBy],
        );
    }

    public function unblock(string $sha256): bool
    {
        return ArchivdHashBlocklist::query()
            ->where('sha256', strtolower(trim($sha256)))
            ->delete() > 0;
    }
}
