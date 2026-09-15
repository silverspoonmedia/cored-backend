<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Models\ArchivdHashBlocklist;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Support\Sha256Checksum;
use Illuminate\Http\Request;

/**
 * sha256 hash blocklist (DMCA / compliance).
 *
 * A file whose checksum appears here must never be publicly shared: enabling
 * anyone_with_link is rejected and guest public access denies as 404. Queries
 * hit a 64-char unique index. {@see isBlocked} / {@see isFileBlocked} stay
 * single exists() with no service-instance memo (Octane-safe). List endpoints
 * prime a request-attribute lookup via {@see blockedSet} so one whereIn covers
 * the page — not Cache, not statics.
 */
class HashBlocklistService
{
    public const REQUEST_CHECKED = 'archivd.hash_blocklist.checked';

    public const REQUEST_BLOCKED = 'archivd.hash_blocklist.blocked';

    public function isBlocked(?string $sha256): bool
    {
        if ($sha256 === null || $sha256 === '') {
            return false;
        }

        return ArchivdHashBlocklist::query()
            ->where('sha256', Sha256Checksum::normalize($sha256))
            ->exists();
    }

    public function isFileBlocked(HostedFile $file): bool
    {
        return $this->isBlocked(is_string($file->checksum) ? $file->checksum : null);
    }

    /**
     * @param  iterable<mixed>  $checksums
     * @return array<string, true>
     */
    public function blockedSet(iterable $checksums): array
    {
        $normalized = $this->uniqueNormalizedChecksums($checksums);

        if ($normalized === []) {
            return [];
        }

        return ArchivdHashBlocklist::query()
            ->whereIn('sha256', $normalized)
            ->pluck('sha256')
            ->mapWithKeys(fn (mixed $sha): array => [(string) $sha => true])
            ->all();
    }

    /**
     * Merge this page's checksums into request attributes (one whereIn for new hashes).
     *
     * @param  iterable<mixed>  $checksums
     */
    public function primeRequestLookup(Request $request, iterable $checksums): void
    {
        $incoming = [];

        foreach ($this->uniqueNormalizedChecksums($checksums) as $checksum) {
            $incoming[$checksum] = true;
        }

        /** @var array<string, true> $checked */
        $checked = $request->attributes->get(self::REQUEST_CHECKED, []);
        $unchecked = array_diff_key($incoming, $checked);

        if ($unchecked === []) {
            return;
        }

        /** @var array<string, true> $blocked */
        $blocked = $request->attributes->get(self::REQUEST_BLOCKED, []);
        $blocked += $this->blockedSet(array_keys($unchecked));
        $checked += $unchecked;

        $request->attributes->set(self::REQUEST_CHECKED, $checked);
        $request->attributes->set(self::REQUEST_BLOCKED, $blocked);
    }

    public function isFileBlockedForRequest(HostedFile $file, Request $request): bool
    {
        $raw = is_string($file->checksum) ? $file->checksum : null;

        if ($raw === null || $raw === '') {
            return false;
        }

        $checksum = Sha256Checksum::normalize($raw);
        /** @var array<string, true> $checked */
        $checked = $request->attributes->get(self::REQUEST_CHECKED, []);

        if (isset($checked[$checksum])) {
            /** @var array<string, true> $blocked */
            $blocked = $request->attributes->get(self::REQUEST_BLOCKED, []);

            return isset($blocked[$checksum]);
        }

        return $this->isBlocked($checksum);
    }

    public function block(string $sha256, ?string $reason = null, ?string $createdBy = null): ArchivdHashBlocklist
    {
        $sha256 = Sha256Checksum::normalize($sha256);

        return ArchivdHashBlocklist::query()->firstOrCreate(
            ['sha256' => $sha256],
            ['reason' => $reason, 'created_by' => $createdBy],
        );
    }

    public function unblock(string $sha256): bool
    {
        return ArchivdHashBlocklist::query()
            ->where('sha256', Sha256Checksum::normalize($sha256))
            ->delete() > 0;
    }

    /**
     * @param  iterable<mixed>  $checksums
     * @return list<string>
     */
    private function uniqueNormalizedChecksums(iterable $checksums): array
    {
        $out = [];

        foreach ($checksums as $checksum) {
            if (! is_string($checksum) || $checksum === '') {
                continue;
            }

            $normalized = Sha256Checksum::normalize($checksum);

            if ($normalized === '') {
                continue;
            }

            $out[$normalized] = true;
        }

        return array_keys($out);
    }
}
