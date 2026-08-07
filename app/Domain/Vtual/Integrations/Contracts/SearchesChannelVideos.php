<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use Illuminate\Support\Collection;

interface SearchesChannelVideos
{
    /**
     * Search channel videos by broadcast event type (YouTube search.list).
     *
     * @param  'live'|'upcoming'|'completed'  $eventType
     * @return Collection<int, string> Platform video external ids
     */
    public function searchChannelVideos(string $channelExternalId, string $eventType, int $limit = 5): Collection;
}
