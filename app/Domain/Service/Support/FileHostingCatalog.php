<?php

namespace App\Domain\Service\Support;

/**
 * File Hosting catalog codes and size constants for Service × FH integration.
 */
final class FileHostingCatalog
{
    public const FULFILLMENT_TARGET = 'file_hosting';

    public const CODE_FREE = 'file-hosting.plan.free';

    public const CODE_MAIN_1TB = 'file-hosting.plan.1tb.monthly';

    public const CODE_MAIN_2TB = 'file-hosting.plan.2tb.monthly';

    public const CODE_ADDON_100GB = 'file-hosting.addon.100gb.monthly';

    public const CODE_ADDON_500GB = 'file-hosting.addon.500gb.monthly';

    /** Legacy unlimited POC — retired. */
    public const CODE_LEGACY_UNLIMITED = 'file-hosting.subscription.monthly';

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
}
