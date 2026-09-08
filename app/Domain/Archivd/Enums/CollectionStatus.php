<?php

namespace App\Domain\Archivd\Enums;

/**
 * Microstock collection lifecycle for a Archivd folder. Only folders in a
 * WorkspaceKind::Microstock workspace carry this state; other folders stay NULL.
 *
 * draft     = collection is open for uploads (default once a folder is used as a collection).
 * locked    = uploads disabled; user has described the collection (hint stored).
 * processing= AI vision/thinking pipeline is running per file (non-recursive).
 * review    = AI finished; user curates title/keywords per file.
 * exported  = collection approved; CSV generated.
 * failed    = AI pipeline errored; can be re-locked to retry.
 */
enum CollectionStatus: string
{
    case Draft = 'draft';
    case Locked = 'locked';
    case Processing = 'processing';
    case Review = 'review';
    case Exported = 'exported';
    case Failed = 'failed';

    /**
     * Once a collection leaves the draft/open state it no longer accepts new
     * uploads (upload is re-enabled only implicitly for non-collection folders).
     */
    public function isLockedForUpload(): bool
    {
        return match ($this) {
            self::Draft, self::Failed => false,
            self::Locked, self::Processing, self::Review, self::Exported => true,
        };
    }

    /**
     * A collection can be (re)locked from the open states to (re)start the flow.
     */
    public function canBeLocked(): bool
    {
        return match ($this) {
            self::Draft, self::Failed => true,
            self::Locked, self::Processing, self::Review, self::Exported => false,
        };
    }
}
