<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use Illuminate\Support\Collection;

interface ListsChannelVideos
{
    /**
     * List recent uploads for a channel (newest first).
     *
     * @return Collection<int, string> Platform video external ids
     */
    public function listChannelVideos(string $channelExternalId, int $limit = 50): Collection;
}
