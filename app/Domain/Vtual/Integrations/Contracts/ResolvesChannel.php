<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;

interface ResolvesChannel
{
    /**
     * Resolve a channel/user by public handle (YouTube forHandle) or login (Twitch).
     */
    public function resolveChannel(string $handleOrLogin): ?PlatformChannelDto;
}
