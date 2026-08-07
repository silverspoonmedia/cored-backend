<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Models\Channel;

final class DiscordConnectionDiscoveryResult
{
    /**
     * @param  list<Channel>  $boundPrivate
     * @param  list<string>  $skippedConflicts
     */
    public function __construct(
        public readonly ?Channel $contractChannel,
        public readonly array $boundPrivate,
        public readonly array $skippedConflicts,
        public readonly int $externalLinksUpserted,
    ) {}
}
