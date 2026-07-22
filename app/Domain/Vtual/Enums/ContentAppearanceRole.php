<?php

namespace App\Domain\Vtual\Enums;

/**
 * How a registered channel relates to a canonical content row.
 * Not multi-ownership: primary = host from platform API; others = appearances.
 */
enum ContentAppearanceRole: string
{
    /** Upload/host channel (matches vtual_contents.channel_id). */
    case Primary = 'primary';

    /** Official multi-channel collab partner when known. */
    case Collaborator = 'collaborator';

    /** Sibling/network/brand association (not API host). */
    case Network = 'network';

    /**
     * Roles allowed when attaching a non-primary appearance.
     *
     * @return list<self>
     */
    public static function nonPrimary(): array
    {
        return [self::Collaborator, self::Network];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
