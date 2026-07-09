<?php

namespace App\Domain\Playground\Support\Orders;

use App\Domain\Playground\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * File I/O helpers for order attachments on the configured disk.
 *
 * Browser-direct uploads land on object storage before an order is saved; this
 * class verifies, deletes, and compensates those objects. URL resolution lives
 * on the {@see Attachment} model.
 */
class OrderAttachmentStorage
{
    public const DISK = 's3Custom';

    /**
     * @param  iterable<array<string, mixed>>  $attachments
     */
    public function deleteMany(iterable $attachments): void
    {
        $byDisk = [];

        foreach ($attachments as $attachment) {
            $disk = $attachment['disk'] ?? self::DISK;
            $path = $attachment['path'] ?? null;

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

    public function pathExists(string $path, ?string $disk = null): bool
    {
        return Storage::disk($disk ?? self::DISK)->exists($path);
    }
}
