<?php

namespace App\Domain\Microstock\Enums;

/**
 * Lifecycle of a marketplace credential. `pending` means it was stored but
 * never exercised; `verified` is set only by a transfer that the host accepted;
 * `failed` keeps the reason in `last_error`.
 */
enum MarketplaceCredentialStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
