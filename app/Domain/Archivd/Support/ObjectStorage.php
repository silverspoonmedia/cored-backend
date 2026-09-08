<?php

namespace App\Domain\Archivd\Support;

use Illuminate\Support\Facades\Storage;

class ObjectStorage
{
    public function pathExists(string $path, string $disk): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Read stream for an object (caller must close).
     *
     * @return resource
     */
    public function readStream(string $path, string $disk)
    {
        return Storage::disk($disk)->readStream($path);
    }

    public function get(string $path, string $disk): ?string
    {
        $bytes = Storage::disk($disk)->get($path);

        return is_string($bytes) ? $bytes : null;
    }

    public function put(string $path, string $contents, string $disk): void
    {
        Storage::disk($disk)->put($path, $contents);
    }

    /**
     * @param  iterable<array{path?: string|null, disk?: string|null}>  $objects
     */
    public function deleteMany(iterable $objects): void
    {
        $byDisk = [];

        foreach ($objects as $object) {
            $disk = $object['disk'] ?? (string) config('archivd.disk');
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
