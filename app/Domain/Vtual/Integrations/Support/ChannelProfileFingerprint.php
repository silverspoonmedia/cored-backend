<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;

/**
 * Canonical identity for a channel profile input before platform HTTP resolve.
 */
final class ChannelProfileFingerprint
{
    public static function from(PlatformKey $platform, string $profile): string
    {
        $parsed = ChannelProfileInputParser::parse($platform, $profile);
        $value = $parsed->kind === ChannelProfileInputKind::Handle
            ? strtolower($parsed->value)
            : $parsed->value;

        return $parsed->kind->value.':'.$value;
    }
}
