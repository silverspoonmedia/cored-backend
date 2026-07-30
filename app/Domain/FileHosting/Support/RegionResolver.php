<?php

namespace App\Domain\FileHosting\Support;

use App\Domain\FileHosting\Models\HostedFile;

/**
 * Resolves region keys for disks and decides when region UI should appear.
 *
 * Region keys and their disk names live in config('file-hosting.regions').
 * Disabled region keys live in config('file-hosting.disabled_regions').
 * Legacy disk names map via config('file-hosting.disk_aliases').
 */
class RegionResolver
{
    /**
     * Canonical disk name after applying configured aliases.
     */
    public static function canonicalDisk(string $disk): string
    {
        $aliases = (array) config('file-hosting.disk_aliases', []);

        return (string) ($aliases[$disk] ?? $disk);
    }

    /**
     * Reverse-lookup the region key for a disk name (aliases resolved first).
     * Returns null when the disk is not mapped to any region.
     */
    public static function keyForDisk(string $disk): ?string
    {
        $canonical = self::canonicalDisk($disk);
        $regions = (array) config('file-hosting.regions');

        foreach ($regions as $key => $diskName) {
            if ((string) $diskName === $disk || (string) $diskName === $canonical) {
                return (string) $key;
            }
            if (self::canonicalDisk((string) $diskName) === $canonical) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * All configured region keys (including disabled).
     *
     * @return list<string>
     */
    public static function configuredKeys(): array
    {
        return array_values(array_map('strval', array_keys((array) config('file-hosting.regions'))));
    }

    /**
     * Region keys available for new uploads (regions minus disabled).
     *
     * @return list<string>
     */
    public static function uploadableKeys(): array
    {
        $regions = self::configuredKeys();
        $disabled = (array) config('file-hosting.disabled_regions');

        return array_values(array_diff($regions, $disabled));
    }

    /**
     * Is the given region key blocked for new uploads?
     */
    public static function isDisabled(string $key): bool
    {
        return in_array($key, (array) config('file-hosting.disabled_regions'), true);
    }

    /**
     * Show region column/badge when multi-region is configured or history
     * proves more than one effective storage target was used.
     *
     * History collapses disk_aliases when configured.
     */
    public static function shouldDisplayRegion(): bool
    {
        if (count(self::configuredKeys()) > 1) {
            return true;
        }

        return self::historicalEffectiveDiskCount() > 1;
    }

    /**
     * Distinct HostedFile disks (with trashed), after alias collapse.
     */
    public static function historicalEffectiveDiskCount(): int
    {
        $disks = HostedFile::withTrashed()
            ->whereNotNull('disk')
            ->where('disk', '!=', '')
            ->distinct()
            ->pluck('disk');

        $canonical = [];
        foreach ($disks as $disk) {
            $canonical[self::canonicalDisk((string) $disk)] = true;
        }

        return count($canonical);
    }
}
