<?php

namespace App\Domain\FileHosting\Support;

use Illuminate\Support\Facades\Storage;

class ObjectStorage
{
    public function pathExists(string $path, string $disk): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * @param  iterable<array{path?: string|null, disk?: string|null}>  $objects
     */
    public function deleteMany(iterable $objects): void
    {
        $byDisk = [];

        foreach ($objects as $object) {
            $disk = $object['disk'] ?? (string) config('file-hosting.disk');
            $path = $object['path'] ?? null;

            if (is_string($path) && $path !== '') {
                $byDisk[$disk][] = $path;
            }
        }

        foreach ($byDisk as $disk => $paths) {
            if ($paths !== []) {
                Storage::disk($disk)->delete($paths);
            }
        }
    }
}
