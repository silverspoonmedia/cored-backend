<?php

namespace App\Support\Orders;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores and removes order attachment files on the configured disk.
 *
 * Files live on the `s3Custom` disk (see config/filesystems.php); the database
 * only keeps lightweight metadata per file (`disk`, `path`, `name`, `size`,
 * `mime`). The `path` is the disk-relative key used to build a URL or delete the
 * object; `disk` records where the bytes live. URL resolution (incl. signed-URL
 * caching) lives on the {@see Attachment} model, not here.
 *
 * This class is pure file I/O — it does not touch the database. OrderService
 * decides WHEN to store/keep/forget files and persists the metadata; keeping
 * the two concerns apart is what lets the service compensate (delete freshly
 * uploaded files) if the surrounding DB transaction rolls back.
 */
class OrderAttachmentStorage
{
    public const DISK = 's3Custom';

    private const DIRECTORY = 'orders/attachments';

    /**
     * Store one uploaded file and return its metadata row.
     *
     * @return array{disk: string, path: string, name: string, size: int, mime: string}
     */
    public function store(UploadedFile $file): array
    {
        $path = $file->store(self::DIRECTORY, self::DISK);

        return [
            'disk' => self::DISK,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
        ];
    }

    /**
     * Store many uploaded files.
     *
     * @param  iterable<UploadedFile>  $files
     * @return list<array{disk: string, path: string, name: string, size: int, mime: string}>
     */
    public function storeMany(iterable $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->store($file);
        }

        return $stored;
    }

    /**
     * Delete the objects behind the given metadata rows. Missing keys are
     * ignored so cleanup is always safe to call (e.g. compensating a rollback).
     *
     * @param  iterable<array<string, mixed>>  $attachments
     */
    public function deleteMany(iterable $attachments): void
    {
        $paths = [];

        foreach ($attachments as $attachment) {
            $path = $attachment['path'] ?? null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
