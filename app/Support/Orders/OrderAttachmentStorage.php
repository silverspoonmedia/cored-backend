<?php

namespace App\Support\Orders;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores and removes order attachment files on the configured disk.
 *
 * Files live on the `s3Custom` disk (see config/filesystems.php); the database
 * only keeps lightweight metadata per file (`path`, `name`, `size`, `mime`).
 * The `path` is the disk-relative key used to build a URL or delete the object.
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
     * @return array{path: string, name: string, size: int, mime: string}
     */
    public function store(UploadedFile $file): array
    {
        $path = $file->store(self::DIRECTORY, self::DISK);

        return [
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
     * @return list<array{path: string, name: string, size: int, mime: string}>
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

    /**
     * Public URL for a stored attachment path.
     */
    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }
}
