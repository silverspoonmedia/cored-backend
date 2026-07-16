<?php

namespace App\Domain\FileHosting\Support;

/**
 * Resolves the region key for a disk and reports upload-disabled regions.
 *
 * Region keys and their disk names live in config('file-hosting.regions').
 * Disabled region keys live in config('file-hosting.disabled_regions').
 */
class RegionResolver
{
    /**
     * Reverse-lookup the region key for a disk name.
     * Returns null when the disk is not mapped to any region.
     */
    public static function keyForDisk(string $disk): ?string
    {
        $regions = (array) config('file-hosting.regions');

        foreach ($regions as $key => $diskName) {
            if ($diskName === $disk) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Region keys available for new uploads (regions minus disabled).
     *
     * @return list<string>
     */
    public static function uploadableKeys(): array
    {
        $regions = array_keys((array) config('file-hosting.regions'));
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
}
