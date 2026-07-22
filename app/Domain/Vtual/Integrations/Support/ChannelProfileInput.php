<?php

namespace App\Domain\Vtual\Integrations\Support;

/**
 * Parsed user profile string (id, handle/login, or URL) before platform HTTP resolve.
 */
final class ChannelProfileInput
{
    public function __construct(
        public readonly string $raw,
        public readonly ChannelProfileInputKind $kind,
        public readonly string $value,
    ) {}
}
