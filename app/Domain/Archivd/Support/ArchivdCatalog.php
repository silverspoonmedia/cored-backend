<?php

namespace App\Domain\Archivd\Support;

/**
 * Archivd catalog codes and size constants for the Service × Archivd integration.
 *
 * Owned by the product, never by `silverspoonmedia/billing`: a SKU code and a size
 * constant are product knowledge, and the billing package must stay sellable
 * without dragging Archivd's catalog into a downstream project.
 */
final class ArchivdCatalog
{
    public const FULFILLMENT_TARGET = 'archivd';

    public const CODE_FREE = 'archivd.plan.free';

    public const CODE_MAIN_1TB = 'archivd.plan.1tb.monthly';

    public const CODE_MAIN_2TB = 'archivd.plan.2tb.monthly';

    public const CODE_ADDON_100GB = 'archivd.addon.100gb.monthly';

    public const CODE_ADDON_500GB = 'archivd.addon.500gb.monthly';

    /** Stackable +1 owned team workspace slot per unit ($1 POC). storage_bytes = 0. */
    public const CODE_ADDON_WORKSPACE_SLOT = 'archivd.addon.workspace-slot.monthly';

    public const ENTITLEMENT_WORKSPACE_SLOTS = 'workspace_slots';

    /** Base owned active workspaces included with any paid Archivd storage plan. */
    public const BASE_OWNED_WORKSPACE_SLOTS = 10;

    /** Legacy unlimited POC — retired. */
    public const CODE_LEGACY_UNLIMITED = 'archivd.subscription.monthly';

    public const GIB = 1024 * 1024 * 1024;

    public const TIB = 1024 * 1024 * 1024 * 1024;

    public static function freeBytes(): int
    {
        return 5 * self::GIB;
    }

    public static function main1TbBytes(): int
    {
        return self::TIB;
    }

    public static function main2TbBytes(): int
    {
        return 2 * self::TIB;
    }

    public static function addon100GbBytes(): int
    {
        return 100 * self::GIB;
    }

    public static function addon500GbBytes(): int
    {
        return 500 * self::GIB;
    }

    public static function isFreeCode(?string $code): bool
    {
        return $code === self::CODE_FREE;
    }

    public static function isWorkspaceSlotCode(?string $code): bool
    {
        return $code === self::CODE_ADDON_WORKSPACE_SLOT;
    }
}
