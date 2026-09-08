<?php

namespace App\Domain\Archivd\Support;

use Illuminate\Support\Str;

/**
 * Maps DB object id (path column) ↔ full S3 object key.
 *
 * DB stores only the object id (40 lowercase alnum chars).
 * S3 key is always: {directory}/{user_id}/{objectId}
 */
class ObjectKeyResolver
{
    public const OBJECT_ID_LENGTH = 40;

    public function generateObjectId(): string
    {
        return Str::lower(Str::random(self::OBJECT_ID_LENGTH));
    }

    public function isValidObjectId(string $objectId): bool
    {
        return (bool) preg_match('/^[a-z0-9]{'.self::OBJECT_ID_LENGTH.'}$/', $objectId);
    }

    public function objectKey(string $userId, string $objectId): string
    {
        $directory = trim((string) config('archivd.directory'), '/');

        return $directory.'/'.$userId.'/'.$objectId;
    }
}
